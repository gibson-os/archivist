<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Session;
use Generator;
use GibsonOS\Core\Dto\Ffmpeg\Media;
use GibsonOS\Core\Dto\Ffmpeg\Stream\Audio;
use GibsonOS\Core\Dto\Parameter\AbstractParameter;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Core\Dto\Web\Request;
use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Exception\DeleteError;
use GibsonOS\Core\Exception\FfmpegException;
use GibsonOS\Core\Exception\FileNotFound;
use GibsonOS\Core\Exception\GetError;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\ProcessError;
use GibsonOS\Core\Exception\WebException;
use GibsonOS\Core\Manager\ModelManager;
use GibsonOS\Core\Service\CryptService;
use GibsonOS\Core\Service\DateTimeService;
use GibsonOS\Core\Service\FfmpegService;
use GibsonOS\Core\Service\ProcessService;
use GibsonOS\Core\Service\WebService;
use GibsonOS\Module\Archivist\Dto\Audible\TitleParts;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Exception\BrowserException;
use GibsonOS\Module\Archivist\Exception\StrategyException;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Service\BrowserService;
use JsonException;
use Psr\Log\LoggerInterface;
use ReflectionException;

class AudibleStrategy extends AbstractWebStrategy
{
    private const URL = 'https://audible.de/';

    private const KEY_EMAIL = 'email';

    private const KEY_PASSWORD = 'password';

    private const KEY_CAPTCHA = 'guess';

    private const KEY_CAPTCHA_IMAGE = 'captchaImage';

    private const KEY_STEP = 'step';

    private const STEP_LOGIN = 0;

    private const STEP_CAPTCHA = 1;

    private const STEP_LIBRARY = 2;

    private const LINK_LIBRARY = 'Bibliothek';

    private const EXPRESSION_PODCAST = '(adbl-episodes-link)[^<]*<[^<]*<[^<]*<[^<]*<[^<]*<[^<]*href="([^"]*)"[^<]*<[^<]*chevron-container.+?</a>.+?';

    private const EXPRESSION_NOT_PODCAST = '(adbl-lib-action-download)[^<]*<a[^<]*href="([^"]*)"[^<]*<[^<]*<[^<]*Herunterladen.+?</a>.+?';

    public function __construct(
        BrowserService $browserService,
        WebService $webService,
        LoggerInterface $logger,
        CryptService $cryptService,
        DateTimeService $dateTimeService,
        ModelManager $modelManager,
        private readonly FfmpegService $ffmpegService,
        private readonly ProcessService $processService
    ) {
        parent::__construct($browserService, $webService, $logger, $cryptService, $dateTimeService, $modelManager);
    }

    public function getName(): string
    {
        return 'Audible';
    }

    public function getAccountParameters(Strategy $strategy): array
    {
        return [
            self::KEY_EMAIL => (new StringParameter('E-Mail'))->setInputType(StringParameter::INPUT_TYPE_EMAIL),
            self::KEY_PASSWORD => (new StringParameter('Passwort'))->setInputType(StringParameter::INPUT_TYPE_PASSWORD),
        ];
    }

    public function setAccountParameters(Account $account, array $parameters): void
    {
        $configuration = $account->getConfiguration();
        $configuration[self::KEY_EMAIL] = $this->cryptService->encrypt(
            $parameters[self::KEY_EMAIL]
            ?? $this->cryptService->decrypt($configuration[self::KEY_EMAIL])
        );
        $configuration[self::KEY_PASSWORD] = $this->cryptService->encrypt(
            $parameters[self::KEY_PASSWORD]
            ?? $this->cryptService->decrypt($configuration[self::KEY_PASSWORD])
        );
        $account->setConfiguration($configuration);
    }

    /**
     * @throws StrategyException
     *
     * @return AbstractParameter[]
     */
    public function getExecuteParameters(Account $account): array
    {
        $executionParameters = $account->getExecutionParameters();

        return match ($executionParameters[self::KEY_STEP] ?? self::STEP_LOGIN) {
            self::STEP_LOGIN => throw new StrategyException('Not logged in!'),
            self::STEP_CAPTCHA => [
                self::KEY_CAPTCHA_IMAGE => (new StringParameter('Captcha'))
                    ->setImage($account->getExecutionParameters()[self::KEY_CAPTCHA_IMAGE]),
            ],
            self::STEP_LIBRARY => [],
            default => throw new StrategyException(sprintf(
                'Unknown audible step %s',
                $executionParameters[self::KEY_STEP]
            ))
        };
    }

    /**
     * @throws BrowserException
     * @throws ElementNotFoundException
     * @throws StrategyException
     */
    public function setExecuteParameters(Account $account, array $parameters): bool
    {
        $executionParameters = $account->getExecutionParameters();

        return match ($executionParameters[self::KEY_STEP] ?? self::STEP_LOGIN) {
            self::STEP_LOGIN => $this->validateLogin($account),
            self::STEP_CAPTCHA => $this->validateCaptcha($account, $parameters),
            self::STEP_LIBRARY => true,
            default => throw new StrategyException(sprintf(
                'Unknown audible step %s',
                $executionParameters[self::KEY_STEP]
            ))
        };
    }

    /**
     * @throws BrowserException
     * @throws DateTimeError
     * @throws JsonException
     * @throws ReflectionException
     * @throws SaveError
     */
    public function getFiles(Account $account): Generator
    {
        $session = $this->getSession($account);
        $page = $session->getPage();

        try {
            while (true) {
                yield from $this->getFilesFromPage($account);

                $link = $page->findLink('Eine Seite vorwärts');

                if (
                    $link === null ||
                    $link->getParent()->hasClass('bc-button-disabled')
                ) {
                    return;
                }

                $this->logger->info('Open next page');
                $link->click();
                $this->browserService->waitForElementById($session, 'lib-subheader-actions');
            }
        } catch (ElementNotFoundException) {
            // do nothing
        }
    }

    /**
     * @throws BrowserException
     * @throws DateTimeError
     * @throws SaveError
     * @throws JsonException
     * @throws ReflectionException
     */
    private function getFilesFromPage(Account $account): Generator
    {
        $expression = '(' . self::EXPRESSION_NOT_PODCAST . '|' . self::EXPRESSION_PODCAST . ')';
        $session = $this->getSession($account);
        $page = $session->getPage();

        $pageParts = explode('class="adbl-library-content-row"', $page->getContent());

        $expression =
            'bc-size-headline3">([^<]*).+?(Serie.+?<a[^>]*>([^<]*)</a>[^<]*(<span class="bc-text">\s*Titel (\S*))?.+?)?summaryLabel.+?' .
            $expression .
            'bc-spacing-top-base'
        ;

        foreach ($pageParts as $pagePart) {
            $this->logger->debug(sprintf('Search #%s# in %s', $expression, $pagePart));
            $matches = ['', '', '', '', '', '', '', '', '', ''];

            if (preg_match('#' . $expression . '#s', $pagePart, $matches) !== 1) {
                continue;
            }

            $titleParts = new TitleParts($matches[1], $matches[3], $matches[5]);

            if (count($matches) === 11) { // Podcast
                $this->modelManager->saveWithoutChildren($account->setMessage(sprintf('Überprüfe %s', $matches[1])));
                $this->logger->info(sprintf('Open podcast page %s', self::URL . $matches[10]));
                $currentUrl = $session->getCurrentUrl();
                $this->browserService->goto($session, $matches[10]);
                $this->browserService->waitForElementById($session, 'lib-subheader-actions');
                $titleParts->setSeries($titleParts->getTitle());

                foreach ($this->getFiles($account) as $file) {
                    if (!$file instanceof File) {
                        continue;
                    }

                    $titleParts->setTitle($file->getName());

                    yield new File($this->cleanTitle($titleParts), $file->getPath(), $file->getCreateDate(), $account);
                }

                $this->modelManager->saveWithoutChildren($account->setMessage('Gehe zurück zur Bibliothek'));
                $this->logger->info(sprintf('Go back to %s', $currentUrl));
                $this->browserService->goto($session, $currentUrl);
                $this->browserService->waitForElementById($session, 'lib-subheader-actions');

                continue;
            }

            $series = $titleParts->getSeries();

            if (empty($series)) {
                $this->findSeriesAndEpisode($titleParts);
            }

            $title = $this->cleanTitle($titleParts);
            $this->logger->info(sprintf('Find %s', $title));

            yield new File($title, self::URL . $matches[8], $this->dateTimeService->get(), $account);
        }

        yield null;
    }

    /**
     * @throws DeleteError
     * @throws DriverException
     * @throws FfmpegException
     * @throws FileNotFound
     * @throws GetError
     * @throws JsonException
     * @throws ProcessError
     * @throws SaveError
     * @throws StrategyException
     * @throws WebException
     */
    public function setFileResource(File $file, Account $account): File
    {
        if ($account->getStrategy() !== self::class) {
            throw new StrategyException(sprintf(
                'Class name %s is not equal with %s',
                $account->getStrategy(),
                self::class
            ));
        }

        $response = $this->webService->get(
            (new Request(html_entity_decode($file->getPath())))
                ->setCookieFile($this->browserService->createCookieFile($this->getSession($account)))
        );

        $resource = $response->getBody()->getResource();

        if ($resource === null) {
            throw new StrategyException('File is empty!');
        }

        $tmpFileName = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'audible' . uniqid() . '.aax';
        $tmpFileNameMp3 = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'audible' . uniqid() . '.mp3';

        $newFile = fopen($tmpFileName, 'w');
        stream_copy_to_stream($resource, $newFile);
        fclose($newFile);

        $this->modelManager->saveWithoutChildren($account->setMessage(sprintf('Ermittel Checksumme für %s', $file->getName())));
        $this->logger->info(sprintf('Get checksum for %s', $file->getName()));
        $checksum = $this->ffmpegService->getChecksum($tmpFileName);

        $this->modelManager->saveWithoutChildren($account->setMessage(sprintf(
            'Ermittel Activation Bytes mit Checksumme %s für %s',
            $checksum,
            $file->getName()
        )));
        $this->logger->info(sprintf('Get activation bytes for checksum %s', $checksum));
        $activationBytes = $this->getActivationBytes($checksum);
        $file->setResource($resource, $response->getBody()->getLength());

        $this->modelManager->saveWithoutChildren($account->setMessage(sprintf('Konvertiere %s', $file->getName())));
        $this->logger->info('Convert');
        $this->ffmpegService->convert(
            (new Media($tmpFileName))->setAudioStreams(['0:a' => new Audio()])->selectAudioStream('0:a'),
            $tmpFileNameMp3,
            null,
            'libmp3lame',
            ['activation_bytes' => $activationBytes]
        );
        $mp3File = fopen($tmpFileNameMp3, 'r');
        $file->setResource($mp3File, filesize($tmpFileNameMp3));
        unlink($tmpFileNameMp3);
        unlink($tmpFileName);

        return $file;
    }

    /**
     * @throws ElementNotFoundException
     */
    public function unload(Account $account): void
    {
        $session = $this->getSession($account);
        $page = $session->getPage();
        $page->clickLink('Abmelden');
        $session->stop();
    }

    public function getLockName(Account $account): string
    {
        return 'audible';
    }

    private function findSeriesAndEpisode(TitleParts $titleParts): void
    {
        $splitTitle = explode(':', str_ireplace(['staffel'], '', $titleParts->getTitle()));

        if (count($splitTitle) !== 2) {
            return;
        }

        $matches = ['', '', ''];

        if (preg_match('/(.*)\s([\d\W]?\d)\s*$/', $splitTitle[1], $matches) !== 1) {
            return;
        }

        $titleParts->setSeries(trim($matches[1]) ?: trim($splitTitle[0]));
        $titleParts->setEpisode(trim($matches[2]));
    }

    private function cleanTitle(TitleParts $titleParts): string
    {
        $cleanTitle = $titleParts->getTitle();
        $cleanTitleParts = explode(':', $cleanTitle);
        $series = $titleParts->getSeries();

        if (
            !empty($series) &&
            count($cleanTitleParts) === 2 &&
            mb_stripos($cleanTitleParts[0], $series) === 0 &&
            mb_stripos($cleanTitleParts[1], $series) === false
        ) {
            $cleanTitle = $cleanTitleParts[1] . ':' . $cleanTitleParts[0];
        }

        $episode = $titleParts->getEpisode();
        $cleanTitle = preg_replace(
            '/(\b)(' . preg_quote($series, '/') . '|' . preg_quote($episode, '/') . ')(\b)/i',
            '$1$3',
            $cleanTitle
        );

        if (!empty($series)) {
            $cleanTitle = preg_replace('/:.*/s', '', $cleanTitle);
        }

        $cleanTitle = trim($cleanTitle);

        if (empty($cleanTitle)) {
            $cleanTitle = $series;
        }

        $cleanTitle = preg_replace('/^[-:._]*/', '', $cleanTitle);
        $cleanTitle = preg_replace('/[-:._]*$/', '', $cleanTitle);
        $cleanTitle = preg_replace('/:/', ' - ', $cleanTitle);
        $cleanTitle = str_replace('/', ' ', $cleanTitle);
        $cleanTitle = preg_replace('/\s\.\s/', ' ', $cleanTitle);

        if (!empty($episode)) {
            $cleanTitle = $episode . ' ' . $cleanTitle;
        }

        return
            (empty($series) ? '' : '[' . trim($series) . '] ') .
            trim(preg_replace('/\s{2,}/s', ' ', $cleanTitle))
        ;
    }

    /**
     * @throws StrategyException
     * @throws ProcessError
     */
    private function getActivationBytes(string $checksum): string
    {
        $rcrack = realpath(
            __DIR__ . DIRECTORY_SEPARATOR .
            '..' . DIRECTORY_SEPARATOR .
            '..' . DIRECTORY_SEPARATOR .
            'bin' . DIRECTORY_SEPARATOR .
            'inAudible-NG-tables' . DIRECTORY_SEPARATOR
        );
        $rcrackProccess = $this->processService->open(sprintf('cd %s && ./rcrack . -h %s', $rcrack, $checksum), 'r');

        while ($out = fgets($rcrackProccess)) {
            $matches = ['', ''];

            if (preg_match('/hex:(\w*)/', $out, $matches) !== 1) {
                continue;
            }

            $this->processService->close($rcrackProccess);

            return $matches[1];
        }

        $this->processService->close($rcrackProccess);

        throw new StrategyException('Activation bytes not found!');
    }

    /**
     * @throws BrowserException
     */
    private function setCaptchaStep(Session $session, Account $account): void
    {
        $executionParameters = $account->getExecutionParameters();
        $captchaImage = $this->browserService->waitForElementById($session, 'auth-captcha-image');
        $captchaImageSource = $captchaImage->getAttribute('src') ?? '';
        $executionParameters[self::KEY_SESSION] = serialize($session);
        $executionParameters[self::KEY_CAPTCHA_IMAGE] = $captchaImageSource;
        $executionParameters[self::KEY_STEP] = self::STEP_CAPTCHA;
        $account->setExecutionParameters($executionParameters);
    }

    /**
     * @throws BrowserException
     * @throws ElementNotFoundException
     */
    private function validateCaptcha(Account $account, array $parameters): bool
    {
        $configuration = $account->getConfiguration();
        $email = $this->cryptService->decrypt($configuration[self::KEY_EMAIL]);
        $password = $this->cryptService->decrypt($configuration[self::KEY_PASSWORD]);
        $session = $this->getSession($account);
        $page = $session->getPage();
        $this->browserService->fillFormFields($session, [
            self::KEY_EMAIL => $email,
            self::KEY_PASSWORD => $password,
            self::KEY_CAPTCHA => $parameters[self::KEY_CAPTCHA_IMAGE],
        ]);
        $page->pressButton('signInSubmit');

        try {
            $this->loadLibrary($session, $account);
        } catch (BrowserException) {
            $this->setCaptchaStep($session, $account);

            return false;
        }

        return true;
    }

    /**
     * @throws BrowserException
     * @throws ElementNotFoundException
     */
    private function validateLogin(Account $account): bool
    {
        $configuration = $account->getConfiguration();
        $session = $this->browserService->getSession();
        $email = $this->cryptService->decrypt($configuration[self::KEY_EMAIL]);
        $password = $this->cryptService->decrypt($configuration[self::KEY_PASSWORD]);
        $page = $this->browserService->loadPage($session, self::URL);
        $page->clickLink('Anmelden');
        $this->browserService->waitForElementById($session, 'ap_email');

        $this->browserService->fillFormFields($session, [
            self::KEY_EMAIL => $email,
            self::KEY_PASSWORD => $password,
        ]);
        $page->pressButton('signInSubmit');

        try {
            $this->loadLibrary($session, $account);
        } catch (BrowserException) {
            $this->setCaptchaStep($session, $account);

            return false;
        }

        return true;
    }

    /**
     * @throws BrowserException
     * @throws ElementNotFoundException
     */
    private function loadLibrary(Session $session, Account $account): void
    {
        $this->browserService->waitForLink($session, self::LINK_LIBRARY, 30000000);
        $page = $this->browserService->getPage($session);
        $page->clickLink(self::LINK_LIBRARY);
        $this->browserService->waitForElementById($session, 'lib-subheader-actions');
        $executionParameters = $account->getExecutionParameters();
        $executionParameters[self::KEY_SESSION] = serialize($session);
        $executionParameters[self::KEY_STEP] = self::STEP_LIBRARY;
        $account->setExecutionParameters($executionParameters);
    }
}
