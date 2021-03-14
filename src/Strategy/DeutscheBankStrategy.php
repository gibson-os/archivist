<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use GibsonOS\Core\Dto\Parameter\AbstractParameter;
use GibsonOS\Core\Dto\Parameter\IntParameter;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Core\Dto\Web\Request;
use GibsonOS\Core\Exception\WebException;
use GibsonOS\Core\Service\WebService;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Exception\StrategyException;
use Psr\Log\LoggerInterface;

class DeutscheBankStrategy implements StrategyInterface
{
    private const URL = 'https://meine.deutsche-bank.de/';

    private WebService $webService;

    private LoggerInterface $logger;

    public function __construct(WebService $webService, LoggerInterface $logger)
    {
        $this->webService = $webService;
        $this->logger = $logger;
    }

    /**
     * @return AbstractParameter[]
     */
    public function getAuthenticationParameters(): array
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
    public function authenticate(Strategy $strategy, array $parameters): void
    {
        $response = $this->webService->post(
            (new Request(self::URL . 'trxm/db/gvo/login/login.do'))
                ->setParameters($parameters)
        );
        $responseBody = $response->getBody()->getContent();
        $photoTanAction = [];
        preg_match('/id="photoTANForm".+?action="([^"]*)"/', $responseBody, $photoTanAction);
        $photoTanGraphic = [];
        preg_match('/id="photoTANGraphic".+?src="([^"]*)"/', $responseBody, $photoTanGraphic);
        $challengeMessage = [];
        preg_match('/id="photoTANGraphic".+?src="([^"]*)"/', $responseBody, $challengeMessage);

        if (
            !isset($photoTanAction[1]) ||
            !isset($photoTanGraphic[1]) ||
            !isset($challengeMessage[1])
        ) {
            throw new StrategyException('No photo TAN found!');
        }

        $strategy->setConfigValue('photoTanAction', $photoTanAction[1]);
        $imageResponse = $this->webService->get(new Request($photoTanGraphic[1]));
        $strategy->setConfigValue('photoTanImage', $imageResponse->getBody()->getContent());
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
     * @param AbstractParameter[] $parameters
     */
    public function authenticate2Factor(Strategy $strategy, array $parameters): void
    {
        $response = $this->webService->post(
            (new Request($strategy->getConfigValue('photoTanAction')))
                ->setParameters($parameters)
        );
    }

    public function getFiles(Strategy $strategy): array
    {
        // TODO: Implement getFiles() method.
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
