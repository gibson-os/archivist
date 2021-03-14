<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use GibsonOS\Core\Dto\Parameter\AbstractParameter;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Core\Dto\Web\Request;
use GibsonOS\Core\Exception\WebException;
use GibsonOS\Core\Service\WebService;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;

class DkbStrategy implements StrategyInterface
{
    private const URL = 'https://www.dkb.de/';

    private WebService $webService;

    public function __construct(WebService $webService)
    {
        $this->webService = $webService;
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

        $response = $this->webService->post(
            (new Request(self::URL . 'banking'))
                ->setParameters($parameters)
                ->setParameter('hiddenSubmit', '')
        );
        $responseBody = $response->getBody()->getContent();
    }

    /**
     * @return AbstractParameter[]
     */
    public function get2FactorAuthenticationParameters(Strategy $strategy): array
    {
        return [
        ];
    }

    public function authenticate2Factor(Strategy $strategy, array $parameters): void
    {
        // /DkbTransactionBanking/content/LoginWithTan/LoginWithTanProcess/LoginWithTanSubmit.xhtml
        // TODO: Implement authenticate2Factor() method.
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
