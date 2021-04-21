<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Generator;
use GibsonOS\Core\Dto\Parameter\AbstractParameter;
use GibsonOS\Core\Dto\Parameter\IntParameter;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Core\Dto\Web\Request;
use GibsonOS\Core\Exception\WebException;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Exception\StrategyException;

class DeutscheBankStrategy extends AbstractWebStrategy
{
    private const URL = 'https://meine.deutsche-bank.de/';

    private const STEP_LOGIN = 0;

    private const STEP_TAN = 0;

    public function getName(): string
    {
        return 'Deutsche Bank';
    }

    /**
     * @return AbstractParameter[]
     */
    public function getConfigurationParameters(Strategy $strategy): array
    {
        switch ($strategy->getConfigStep()) {
            case self::STEP_TAN: return $this->getTanParameters();
            //case self::STEP_PATH: return $this->getPathParameters($strategy);
            default: return $this->getLoginParameters();
        }
    }

    /**
     * @param array<string, string> $parameters
     *
     * @throws StrategyException
     * @throws WebException
     */
    public function saveConfigurationParameters(Strategy $strategy, array $parameters): bool
    {
        switch ($strategy->getConfigStep()) {
            case self::STEP_TAN:
                $this->validateTan($strategy, $parameters);

                return false;
//            case self::STEP_PATH:
//                $strategy->setConfigValue('path', $parameters['path']);
//
//                return true;
            default:
                $this->login($strategy, $parameters);

                return false;
        }

        $response = $this->browserService->post(
            (new Request(self::URL . 'trxm/db/gvo/login/login.do'))
                ->setParameters($parameters)
        );
        $responseBody = $response->getBody()->getContent();
        $cookieFile = $response->getCookieFile();
        $imageResponse = $this->browserService->get(
            (new Request($this->getResponseValue($responseBody, 'id', 'photoTANGraphic', 'src')))
                ->setCookieFile($cookieFile)
        );
        $strategy
            ->setConfigValue(
                'photoTanAction',
                $this->getResponseValue($responseBody, 'id', 'photoTANForm', 'action')
            )
            ->setConfigValue(
                'challengeMessage',
                $this->getResponseValue($responseBody, 'id', 'challengeMessage', 'value')
            )
            ->setConfigValue('photoTanImage', $imageResponse->getBody()->getContent())
            ->setConfigValue('cookieFile', $cookieFile)
        ;

        return true;
    }

    /**
     * @param array<string, string> $parameters
     */
    public function authenticate2Factor(Strategy $strategy, array $parameters): void
    {
        $response = $this->browserService->post(
            (new Request($strategy->getConfigValue('photoTanAction')))
                ->setParameter('challengeMessage', $strategy->getConfigValue('challengeMessage'))
                ->setParameter('tan', $strategy->getConfigValue($parameters['photoTan']))
                ->setCookieFile($strategy->getConfigValue('cookieFile'))
        );
    }

    public function getFiles(Strategy $strategy): Generator
    {
        yield null;
    }

    /**
     * @throws StrategyException
     * @throws WebException
     */
    public function setFileResource(File $file): File
    {
        $responseBody = $this->browserService->get(new Request($file->getPath()))->getBody();
        $resource = $responseBody->getResource();

        if ($resource === null) {
            throw new StrategyException('No response!');
        }

        return $file->setResource($resource, $responseBody->getLength());
    }

    public function unload(Strategy $strategy): void
    {
    }

    private function getLoginParameters(): array
    {
        return [
            'branch' => (new IntParameter('Filiale'))->setRange(1, 999),
            'account' => (new IntParameter('Konto'))->setRange(1, 9999999),
            'subAccount' => (new IntParameter('Unterkonto'))->setRange(0, 99),
            'pin' => (new StringParameter('PIN'))->setInputType(StringParameter::INPUT_TYPE_PASSWORD),
        ];
    }

    private function getTanParameters(): array
    {
        return [
            'photoTan' => new IntParameter('Photo TAN'),
        ];
    }

    private function login(Strategy $strategy, array $parameters): void
    {
        $response = $this->browserService->post(
            (new Request(self::URL . 'trxm/db/gvo/login/login.do'))
                ->setParameters($parameters)
                ->setParameter('gvo', 'DisplayFinancialOverview')
                ->setParameter('process', 'DisplayFinancialOverview')
                ->setParameter('wknOrIsin', '')
                ->setParameter('quantity', '')
                ->setParameter('fingerprintToken', '')
                ->setParameter('fingerprintTokenVersion', '')
                ->setParameter('updateFingerprintToken', 'false')
                ->setParameter('javascriptEnabled', 'false')
                ->setParameter('quickLink', 'setupNachrichtenbox')
        );
        $responseBody = $response->getBody()->getContent();

//        try {
        $this->getResponseValue($responseBody, 'name', 'tan', 'id');
//        } catch (StrategyException $e) {
//            $response = $this->webService->post(
//                (new Request(
//                    self::URL . 'DkbTransactionBanking/content/LoginWithTan/LoginWithTanProcess/InfoOpenLoginRequest.xhtml'
//                ))
//                    ->setCookieFile($response->getCookieFile())
//                    ->setParameter('$event', 'next')
//            );
//            $responseBody = $response->getBody()->getContent();
//        }

        $this->logger->debug('Authenticate response: ' . $responseBody);
        $strategy
            ->setConfigValue('cookieFile', $response->getCookieFile())
            ->setConfigValue('branch', $this->cryptService->encrypt($parameters['branch']))
            ->setConfigValue('account', $this->cryptService->encrypt($parameters['account']))
            ->setConfigValue('subAccount', $this->cryptService->encrypt($parameters['subAccount']))
            ->setConfigValue('pin', $this->cryptService->encrypt($parameters['pin']))
            ->setNextConfigStep()
        ;
    }

    private function validateTan(Strategy $strategy, array $parameters): void
    {
    }
}
