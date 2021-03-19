<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use GibsonOS\Core\Dto\Parameter\AbstractParameter;
use GibsonOS\Core\Dto\Parameter\IntParameter;
use GibsonOS\Core\Dto\Parameter\OptionParameter;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Core\Dto\Web\Request;
use GibsonOS\Core\Exception\WebException;
use GibsonOS\Core\Service\CryptService;
use GibsonOS\Core\Service\DateTimeService;
use GibsonOS\Core\Service\WebService;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Exception\StrategyException;
use Psr\Log\LoggerInterface;

class DkbStrategy extends AbstractWebStrategy
{
    private const URL = 'https://www.dkb.de/';

    private DateTimeService $dateTimeService;

    public function __construct(
        WebService $webService,
        LoggerInterface $logger,
        CryptService $cryptService,
        DateTimeService $dateTimeService
    ) {
        parent::__construct($webService, $logger, $cryptService);
        $this->dateTimeService = $dateTimeService;
    }

    public function getName(): string
    {
        return 'DKB';
    }

    /**
     * @throws StrategyException
     * @throws WebException
     *
     * @return AbstractParameter[]
     */
    public function getConfigurationParameters(Strategy $strategy): array
    {
        if (
            $strategy->hasConfigValue('username') &&
            $strategy->hasConfigValue('password')
        ) {
            $this->login($strategy, [
                'j_username' => $this->cryptService->decrypt($strategy->getConfigValue('username')),
                'j_password' => $this->cryptService->decrypt($strategy->getConfigValue('password')),
            ]);
        }

        if (!$strategy->hasConfigValue('step')) {
            return $this->getLoginParameters();
        }

        switch ($strategy->getConfigValue('step')) {
            case 'tan': return $this->getTanParameters();
            case 'path': return $this->getPathParameters($strategy);
            default: return $this->getLoginParameters();
        }
    }

    /**
     * @throws StrategyException
     * @throws WebException
     */
    public function saveConfigurationParameters(Strategy $strategy, array $parameters): bool
    {
        if (!$strategy->hasConfigValue('step')) {
            $this->login($strategy, $parameters);

            return false;
        }

        switch ($strategy->getConfigValue('step')) {
            case 'tan':
                $this->validateTan($strategy, $parameters);

                return false;
            case 'path':
                // @todo was machste hier?
                return true;
            default:
                $this->login($strategy, $parameters);

                return false;
        }
    }

    /**
     * @throws WebException
     */
    public function getFiles(Strategy $strategy, array $parameters): array
    {
        return $this->getFilesFromPage($strategy, $parameters['path']);
    }

    /**
     * @throws WebException
     */
    private function getFilesFromPage(Strategy $strategy, string $url): array
    {
        $response = $this->webService->get(
            (new Request($url))
                ->setCookieFile($strategy->getConfigValue('cookieFile'))
        );
        $responseBody = $response->getBody()->getContent();

        $fileMatches = [[], [], [], [], [], []];
        preg_match_all(
            '/(\d{2})\.(\d{2})\.(\d{4})<\/div>\s*<a.+?href="([^"]*)".+?tid="getMailboxAttachment">([^<]+)/gs',
            $responseBody,
            $fileMatches
        );
        $files = [];

        foreach ($fileMatches[5] as $id => $fileName) {
            $files[] = new File(
                $fileName,
                $fileMatches[4][$id],
                $this->dateTimeService->get($fileMatches[3][$id] . '-' . $fileMatches[2][$id] . $fileMatches[1][$id]),
                $strategy
            );
        }

        $nextPage = [];
        preg_match(
            '/href="([^"]*)"[ ^>]*class="pager-navigator-link"><img\s*src="[^"]*Next/',
            $responseBody,
            $nextPage
        );

        if (isset($nextPage[1])) {
            $files += $this->getFilesFromPage($strategy, $nextPage[1]);
        }

        return $files;
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

    private function getPathParameters(Strategy $strategy): array
    {
        return [
            'path' => new OptionParameter('Verzeichnis', $strategy->getConfigValue('directories')),
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
        $strategy
            ->setConfigValue('cookieFile', $response->getCookieFile())
            ->setConfigValue('username', $this->cryptService->encrypt($parameters['j_username']))
            ->setConfigValue('password', $this->cryptService->encrypt($parameters['j_password']))
            ->setConfigValue('next', 'tan')
        ;
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

        $response = $this->webService->post(
            (new Request(self::URL . 'banking/postfach'))
                ->setCookieFile($strategy->getConfigValue('cookieFile'))
        );
        $responseBody = $response->getBody()->getContent();

        $links = [[], [], []];
        preg_match_all('/href="([^"]*)".+?class="evt-gotoFolder"[^>]*>([^<]*)/', $responseBody, $links);

        $directories = [];

        foreach ($links[1] as $key => $link) {
            $directories[$links[2][$key]] = $link;
        }

        $strategy
            ->setConfigValue('directories', $directories)
            ->setConfigValue('next', 'path')
        ;
    }
}
