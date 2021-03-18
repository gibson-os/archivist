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

class DeutscheBankStrategy extends AbstractWebStrategy
{
    private const URL = 'https://meine.deutsche-bank.de/';

    public function getName(): string
    {
        return 'Deutsche Bank';
    }

    /**
     * @return AbstractParameter[]
     */
    public function getConfigurationParameters(Strategy $strategy): array
    {
        return [
            'branch' => (new IntParameter('Filiale'))->setRange(1, 999),
            'account' => (new IntParameter('Konto'))->setRange(1, 9999999),
            'subAccount' => (new IntParameter('Unterkonto'))->setRange(0, 99),
            'pin' => (new StringParameter('PIN'))->setInputType(StringParameter::INPUT_TYPE_PASSWORD),
        ];
    }

    /**
     * @param array<string, string> $parameters
     *
     * @throws StrategyException
     * @throws WebException
     */
    public function saveConfigurationParameters(Strategy $strategy, array $parameters): bool
    {
        $response = $this->webService->post(
            (new Request(self::URL . 'trxm/db/gvo/login/login.do'))
                ->setParameters($parameters)
        );
        $responseBody = $response->getBody()->getContent();
        $cookieFile = $response->getCookieFile();
        $imageResponse = $this->webService->get(
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
     * @return AbstractParameter[]
     */
    public function get2FactorAuthenticationParameters(Strategy $strategy): array
    {
        return [
            'photoTan' => new IntParameter('Photo TAN'),
        ];
    }

    /**
     * @param array<string, string> $parameters
     */
    public function authenticate2Factor(Strategy $strategy, array $parameters): void
    {
        $response = $this->webService->post(
            (new Request($strategy->getConfigValue('photoTanAction')))
                ->setParameter('challengeMessage', $strategy->getConfigValue('challengeMessage'))
                ->setParameter('tan', $strategy->getConfigValue($parameters['photoTan']))
                ->setCookieFile($strategy->getConfigValue('cookieFile'))
        );
    }

    public function getFiles(Strategy $strategy): array
    {
        return [];
    }

    /**
     * @throws StrategyException
     * @throws WebException
     */
    public function setFileResource(File $file): File
    {
        $responseBody = $this->webService->get(new Request($file->getPath()))->getBody();
        $resource = $responseBody->getResource();

        if ($resource === null) {
            throw new StrategyException('No response!');
        }

        return $file->setResource($resource, $responseBody->getLength());
    }
}
