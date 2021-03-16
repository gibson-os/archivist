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
    public function getAuthenticationParameters(): array
    {
        return [
            'j_username' => new StringParameter('Anmeldename'),
            'j_password' => new StringParameter('Passwort'),
        ];
    }

    /**
     * @throws WebException
     */
    public function authenticate(Strategy $strategy, array $parameters): void
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
     * @return AbstractParameter[]
     */
    public function get2FactorAuthenticationParameters(Strategy $strategy): array
    {
        return [
            'tan' => (new IntParameter('TAN'))->setRange(0, 999999),
        ];
    }

    public function authenticate2Factor(Strategy $strategy, array $parameters): void
    {
        $response = $this->webService->post(
            (new Request(
                self::URL . 'DkbTransactionBanking/content/LoginWithTan/LoginWithTanProcess/LoginWithTanSubmit.xhtm'
            ))
                ->setCookieFile($strategy->getConfigValue('cookieFile'))
                ->setParameter('tan', $parameters['tan'])
                ->setParameter('$event', 'next')
        );
        $responseBody = $response->getBody()->getContent();
        $this->logger->debug('Response: ' . $responseBody);
    }

    public function getFiles(Strategy $strategy): array
    {
        return [];
    }

    public function setFileResource(File $file): File
    {
        return $file;
    }
}
