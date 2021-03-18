<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use GibsonOS\Core\Dto\Parameter\AbstractParameter;
use GibsonOS\Core\Dto\Parameter\IntParameter;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Core\Dto\Web\Request;
use GibsonOS\Core\Exception\WebException;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Exception\StrategyException;

class DkbStrategy extends AbstractWebStrategy
{
    private const URL = 'https://www.dkb.de/';

    public function getName(): string
    {
        return 'DKB';
    }

    /**
     * @return AbstractParameter[]
     */
    public function getConfigurationParameters(Strategy $strategy): array
    {
        if ($strategy->hasConfigValue('cookieFile')) {
            return $this->getTanParameters();
        }

        return $this->getLoginParameters();
    }

    /**
     * @throws StrategyException
     * @throws WebException
     */
    public function saveConfigurationParameters(Strategy $strategy, array $parameters): bool
    {
        if (isset($parameters['j_username'], $parameters['j_password'])) {
            $this->login($strategy, $parameters);

            return false;
        }

        if (!$strategy->hasConfigValue('cookieFile')) {
            throw new StrategyException('Login required!');
        }

        $this->validateTan($strategy, $parameters);

        return true;
    }

    public function getFiles(Strategy $strategy): array
    {
        $response = $this->webService->post(
            (new Request(self::URL . 'banking/postfach'))
                ->setCookieFile($strategy->getConfigValue('cookieFile'))
        );
        $responseBody = $response->getBody()->getContent();

        $links = [];
        preg_match_all('/href="([^"]*)".+?class="evt-gotoFolder"[^>]*>([^<]*)/', $responseBody, $links);

        return [];
    }

    public function setFileResource(File $file): File
    {
        return $file;
    }

    /**
     * @return AbstractParameter[]
     */
    private function getLoginParameters(): array
    {
        return [
            'j_username' => new StringParameter('Anmeldename'),
            'j_password' => (new StringParameter('Passwort'))->setInputType(StringParameter::INPUT_TYPE_PASSWORD),
        ];
    }

    private function getTanParameters(): array
    {
        return [
            'tan' => (new IntParameter('TAN'))->setRange(0, 999999),
        ];
    }

    /**
     * @throws StrategyException
     * @throws WebException
     */
    private function login(Strategy $strategy, array $parameters): void
    {
        $initResponse = $this->webService->get(new Request(self::URL . 'banking'));
        $initResponseBody = $initResponse->getBody()->getContent();
        $this->logger->debug('Authenticate init response: ' . $initResponseBody);

        $response = $this->webService->post(
            (new Request(self::URL . 'banking'))
                ->setCookieFile($initResponse->getCookieFile())
                ->setParameters($parameters)
                ->setParameter('$event', 'login')
                ->setParameter(
                    '$sID$',
                    $this->getResponseValue($initResponseBody, 'name', '$sID$', 'value')
                )
                ->setParameter(
                    'token',
                    $this->getResponseValue($initResponseBody, 'name', 'token', 'value')
                )
        );
        $responseBody = $response->getBody()->getContent();

        try {
            $this->getResponseValue($responseBody, 'name', 'tan', 'id');
        } catch (StrategyException $e) {
            $response = $this->webService->post(
                (new Request(
                    self::URL . 'DkbTransactionBanking/content/LoginWithTan/LoginWithTanProcess/InfoOpenLoginRequest.xhtml'
                ))
                    ->setCookieFile($initResponse->getCookieFile())
            );
            $responseBody = $response->getBody()->getContent();
        }

        $this->logger->debug('Authenticate response: ' . $responseBody);
        $strategy->setConfigValue('cookieFile', $response->getCookieFile());
    }

    /**
     * @throws WebException
     */
    private function validateTan(Strategy $strategy, array $parameters): void
    {
        $response = $this->webService->post(
            (new Request(
                self::URL . 'DkbTransactionBanking/content/LoginWithTan/LoginWithTanProcess/LoginWithTanSubmit.xhtm'
            ))
                ->setCookieFile($strategy->getConfigValue('cookieFile'))
                ->setParameter('tan', (string) $parameters['tan'])
                ->setParameter('$event', 'next')
        );
        $responseBody = $response->getBody()->getContent();
        $this->logger->debug('Response: ' . $responseBody);
    }
}
