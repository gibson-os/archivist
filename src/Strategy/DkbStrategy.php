<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Behat\Mink\Exception\ElementNotFoundException;
use Generator;
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
use GibsonOS\Module\Archivist\Exception\BrowserException;
use GibsonOS\Module\Archivist\Exception\StrategyException;
use GibsonOS\Module\Archivist\Service\BrowserService;
use Psr\Log\LoggerInterface;

class DkbStrategy extends AbstractWebStrategy
{
    private const URL = 'https://www.dkb.de';

    private const STEP_LOGIN = 0;

    private const STEP_TAN = 1;

    private const STEP_PATH = 2;

    private DateTimeService $dateTimeService;

    public function __construct(
        BrowserService $browserService,
        WebService $webService,
        LoggerInterface $logger,
        CryptService $cryptService,
        DateTimeService $dateTimeService
    ) {
        parent::__construct($browserService, $webService, $logger, $cryptService);
        $this->dateTimeService = $dateTimeService;
    }

    public function getName(): string
    {
        return 'DKB';
    }

    /**
     * @throws BrowserException
     * @throws ElementNotFoundException
     *
     * @return AbstractParameter[]
     */
    public function getConfigurationParameters(Strategy $strategy): array
    {
        if (
            $strategy->getConfigStep() === self::STEP_LOGIN &&
            $strategy->hasConfigValue('username') &&
            $strategy->hasConfigValue('password')
        ) {
            $this->login($strategy, [
                'j_username' => $this->cryptService->decrypt($strategy->getConfigValue('username')),
                'j_password' => $this->cryptService->decrypt($strategy->getConfigValue('password')),
            ]);
        }

        switch ($strategy->getConfigStep()) {
            case self::STEP_TAN: return $this->getTanParameters();
            case self::STEP_PATH: return $this->getPathParameters($strategy);
            default: return $this->getLoginParameters();
        }
    }

    /**
     * @throws BrowserException
     * @throws ElementNotFoundException
     */
    public function saveConfigurationParameters(Strategy $strategy, array $parameters): bool
    {
        switch ($strategy->getConfigStep()) {
            case self::STEP_TAN:
                $this->validateTan($strategy, $parameters);

                return false;
            case self::STEP_PATH:
                $strategy->setConfigValue('path', $parameters['path']);

                return true;
            default:
                $this->login($strategy, $parameters);

                return false;
        }
    }

    /**
     * @throws ElementNotFoundException
     */
    public function getFiles(Strategy $strategy): Generator
    {
        $session = $this->getSession($strategy);
        $page = $session->getPage();
        $page->clickLink($strategy->getConfigValue('path'));

        try {
            while (true) {
                foreach ($this->getFilesFromPage($strategy) as $file) {
                    yield $file;
                }

                $page->clickLink('Nächste Seite');
            }
        } catch (ElementNotFoundException $exception) {
            // do nothing
        }
    }

    /**
     * @return File[]
     */
    private function getFilesFromPage(Strategy $strategy): array
    {
        $session = $this->getSession($strategy);
        $page = $session->getPage();
        $responseBody = $page->getContent();

        $fileMatches = [[], [], [], [], [], []];
        preg_match_all(
            '/(\d{2})\.(\d{2})\.(\d{4})<\/div>\s*<a.+?href="([^"]*)".+?tid="getMailboxAttachment">([^<]+)/s',
            $responseBody,
            $fileMatches
        );
        $files = [];

        foreach ($fileMatches[5] as $id => $fileName) {
            $files[] = new File(
                $fileName,
                self::URL . $fileMatches[4][$id],
                $this->dateTimeService->get($fileMatches[3][$id] . '-' . $fileMatches[2][$id] . '-' . $fileMatches[1][$id]),
                $strategy
            );
        }

        return $files;
    }

    /**
     * @throws StrategyException
     * @throws WebException
     */
    public function setFileResource(File $file): File
    {
        $strategy = $file->getStrategy();

        if ($strategy->getClassName() !== self::class) {
            throw new StrategyException(sprintf(
                'Class name %s is not equal with %s',
                $strategy->getClassName(),
                self::class
            ));
        }

        $response = $this->webService->get(
            (new Request($file->getPath()))
                ->setCookieFile($this->browserService->createCookieFile($this->getSession($strategy)))
        );

        $resource = $response->getBody()->getResource();

        if ($resource === null) {
            throw new StrategyException('File is empty!');
        }

        $file->setResource($resource, $response->getBody()->getLength());

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
     * @throws BrowserException
     * @throws ElementNotFoundException
     */
    private function login(Strategy $strategy, array $parameters): void
    {
        $session = $this->browserService->getSession();
        $page = $this->browserService->loadPage($session, self::URL . '/banking');
        $this->logger->debug('Init page: ' . $page->getContent());
        $this->browserService->fillFormFields($page, $parameters);
        $page->pressButton('Anmelden');

        try {
            $this->browserService->waitForElementById($page, 'tanInputSelector');
        } catch (BrowserException $e) {
            $page->pressButton('Anmeldung bestätigen');
            $this->browserService->waitForElementById($page, 'tanInputSelector');
        }

        $this->logger->debug('Authenticate page: ' . $page->getContent());
        $strategy
            ->setConfigValue('session', serialize($session))
            ->setConfigValue('username', $this->cryptService->encrypt($parameters['j_username']))
            ->setConfigValue('password', $this->cryptService->encrypt($parameters['j_password']))
            ->setNextConfigStep()
        ;
    }

    /**
     * @throws ElementNotFoundException
     */
    public function unload(Strategy $strategy): void
    {
        $session = $this->getSession($strategy);
        $page = $session->getPage();
        $page->clickLink('Abmelden');
        $session->stop();
    }

    /**
     * @throws BrowserException
     * @throws ElementNotFoundException
     */
    private function validateTan(Strategy $strategy, array $parameters): void
    {
        $session = $this->getSession($strategy);
        $page = $session->getPage();
        $this->browserService->fillFormFields($page, ['tan' => $parameters['tan']]);
        $page->pressButton('Anmeldung bestätigen');
        $this->browserService->waitForElementById($page, 'menu_0.4-node');
        $page->clickLink('Postfach');
        $this->browserService->waitForElementById($page, 'welcomeMboTable');

        $links = [[], [], []];
        preg_match_all('/class="evt-gotoFolder[^>]*>(([^<|^\s]*)[^<]*)/', $page->getContent(), $links);

        $directories = [];

        foreach ($links[1] as $key => $link) {
            $directories[$links[2][$key]] = $link;
        }

        $strategy
            ->setConfigValue('directories', $directories)
            ->setNextConfigStep()
        ;
    }
}
