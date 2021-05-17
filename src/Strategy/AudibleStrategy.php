<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\ElementNotFoundException;
use Generator;
use GibsonOS\Core\Dto\Ffmpeg\Media;
use GibsonOS\Core\Dto\Ffmpeg\Stream\Audio;
use GibsonOS\Core\Dto\Parameter\OptionParameter;
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
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Service\BrowserService;
use Psr\Log\LoggerInterface;

class AudibleStrategy extends AbstractWebStrategy
{
    private const URL = 'https://audible.de/';

    private const KEY_EMAIL = 'email';

    private const KEY_PASSWORD = 'password';

    private const KEY_TYPE = 'type';

    private const TYPE_SINGLE = 'single';

    private const TYPE_SERIES = 'series';

    private const TYPE_PODCAST = 'podcast';

    private FfmpegService $ffmpegService;

    private ProcessService $processService;

    public function __construct(
        BrowserService $browserService,
        WebService $webService,
        LoggerInterface $logger,
        CryptService $cryptService,
        DateTimeService $dateTimeService,
        FfmpegService $ffmpegService,
        ProcessService $processService
    ) {
        parent::__construct($browserService, $webService, $logger, $cryptService, $dateTimeService);
        $this->ffmpegService = $ffmpegService;
        $this->processService = $processService;
    }

    public function getName(): string
    {
        return 'Audible';
    }

    public function getConfigurationParameters(Strategy $strategy): array
    {
        $typeParameter = new OptionParameter('Typ', [
            'Einzelne Hörbücher' => self::TYPE_SINGLE,
            'Serien' => self::TYPE_SERIES,
            'Podcast' => self::TYPE_PODCAST,
        ]);

        if (
            $strategy->hasConfigValue(self::KEY_EMAIL) &&
            $strategy->hasConfigValue(self::KEY_PASSWORD)
        ) {
            return [self::KEY_TYPE => $typeParameter];
        }

        return [
            self::KEY_EMAIL => (new StringParameter('E-Mail'))->setInputType(StringParameter::INPUT_TYPE_EMAIL),
            self::KEY_PASSWORD => (new StringParameter('Passwort'))->setInputType(StringParameter::INPUT_TYPE_PASSWORD),
            self::KEY_TYPE => $typeParameter,
        ];
    }

    /**
     * @throws ElementNotFoundException
     * @throws BrowserException
     */
    public function saveConfigurationParameters(Strategy $strategy, array $parameters): bool
    {
        $session = $this->browserService->getSession();
        $page = $this->browserService->loadPage($session, self::URL);
        $page->clickLink('Bereits Kunde? Anmelden');
        $this->browserService->waitForElementById($page, 'ap_email');
        $this->browserService->fillFormFields($page, [
            self::KEY_EMAIL => $parameters[self::KEY_EMAIL]
                ?? $this->cryptService->decrypt($strategy->getConfigValue(self::KEY_EMAIL)),
            self::KEY_PASSWORD => $parameters[self::KEY_PASSWORD]
                ?? $this->cryptService->decrypt($strategy->getConfigValue(self::KEY_PASSWORD)),
        ]);
        $page->pressButton('signInSubmit');
        $this->browserService->waitForLink($page, 'Bibliothek', 60000000);
        $page->clickLink('Bibliothek');
        $this->browserService->waitForElementById($page, 'lib-subheader-actions');

        $strategy
            ->setConfigValue(self::KEY_SESSION, serialize($session))
            ->setConfigValue(self::KEY_TYPE, $parameters[self::KEY_TYPE])
            ->setConfigValue(self::KEY_EMAIL, $this->cryptService->encrypt($parameters[self::KEY_EMAIL]))
            ->setConfigValue(self::KEY_PASSWORD, $this->cryptService->encrypt($parameters[self::KEY_PASSWORD]))
        ;

        return true;
    }

    /**
     * @throws BrowserException
     */
    public function getFiles(Strategy $strategy, Rule $rule, string $type = null): Generator
    {
        $session = $this->getSession($strategy);
        $page = $session->getPage();

        try {
            while (true) {
                yield from $this->getFilesFromPage(
                    $strategy,
                    $rule,
                    $type ?? $strategy->getConfigValue(self::KEY_TYPE)
                );

                $link = $page->findLink('Eine Seite vorwärts');

                if (
                    $link === null ||
                    $link->getParent()->hasClass('bc-button-disabled')
                ) {
                    return;
                }

                $this->logger->info('Open next page');
                $link->click();
                $this->browserService->waitForElementById($page, 'lib-subheader-actions');
            }
        } catch (ElementNotFoundException $exception) {
            // do nothing
        }
    }

    /**
     * @throws BrowserException
     * @throws DateTimeError
     * @throws SaveError
     */
    private function getFilesFromPage(Strategy $strategy, Rule $rule, string $type): Generator
    {
        $expression = 'adbl-lib-action-download[^<]*<a[^<]*href="([^"]*)"[^<]*<[^<]*<[^<]*Herunterladen.+?</a>.+?';

        if ($type === self::TYPE_PODCAST) {
            $expression = 'adbl-episodes-link[^<]*<[^<]*<[^<]*<[^<]*<[^<]*<[^<]*href="([^"]*)"[^<]*<[^<]*chevron-container.+?</a>.+?';
        }

        $session = $this->getSession($strategy);
        $page = $session->getPage();

        $pageParts = explode('class="adbl-library-content-row"', $page->getContent());

        $expression =
            'bc-size-headline3">([^<]*).+?(Serie.+?<a[^>]*>([^<]*)</a>(, Titel (\S*))?.+?)?summaryLabel.+?' .
            $expression .
            'bc-spacing-top-base'
        ;

        foreach ($pageParts as $pagePart) {
            $this->logger->debug(sprintf('Search #%s# in %s', $expression, $pagePart));
            $matches = ['', '', '', '', '', '', ''];

            if (preg_match('#' . $expression . '#s', $pagePart, $matches) !== 1) {
                continue;
            }

            $titleParts = new TitleParts($matches[1], $matches[3], $matches[5]);

            if ($type === self::TYPE_PODCAST) {
                $rule->setMessage(sprintf('Überprüfe %s', $matches[1]))->save();
                $this->logger->info(sprintf('Open podcast page %s', self::URL . $matches[6]));
                $currentUrl = $session->getCurrentUrl();
                $this->browserService->goto($session, $matches[6]);
                $this->browserService->waitForElementById($page, 'lib-subheader-actions');
                $titleParts->setSeries($titleParts->getTitle());

                foreach ($this->getFiles($strategy, $rule, self::TYPE_SINGLE) as $file) {
                    if (!$file instanceof File) {
                        continue;
                    }

                    $titleParts->setTitle($file->getName());

                    yield new File($this->cleanTitle($titleParts), $file->getPath(), $file->getCreateDate(), $strategy);
                }

                $rule->setMessage('Gehe zurück zur Bibliothek')->save();
                $this->logger->info(sprintf('Go back to %s', $currentUrl));
                $this->browserService->goto($session, $currentUrl);
                $this->browserService->waitForElementById($page, 'lib-subheader-actions');

                continue;
            }

            $series = $titleParts->getSeries();

            if (empty($series)) {
                $this->findSeriesAndEpisode($titleParts);
            }

            if (
                (empty($series) && $type === self::TYPE_SERIES) ||
                (!empty($series) && $type === self::TYPE_SINGLE)
            ) {
                continue;
            }

            $title = $this->cleanTitle($titleParts);
            $this->logger->info(sprintf('Find %s', $title));

            yield new File($title, $matches[6], $this->dateTimeService->get(), $strategy);
        }

        yield null;
    }

    /**
     * @throws DateTimeError
     * @throws DeleteError
     * @throws DriverException
     * @throws FfmpegException
     * @throws FileNotFound
     * @throws GetError
     * @throws ProcessError
     * @throws StrategyException
     * @throws WebException
     * @throws SaveError
     */
    public function setFileResource(File $file, Rule $rule): File
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
            (new Request(html_entity_decode($file->getPath())))
                ->setCookieFile($this->browserService->createCookieFile($this->getSession($strategy)))
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

        $rule->setMessage(sprintf('Ermittel Checksumme für %s', $file->getName()))->save();
        $this->logger->info(sprintf('Get checksum for %s', $file->getName()));
        $checksum = $this->ffmpegService->getChecksum($tmpFileName);

        $rule->setMessage(sprintf('Ermittel Activation Bytes mit Checksumme %s für %s', $checksum, $file->getName()))->save();
        $this->logger->info(sprintf('Get activation bytes for checksum %s', $checksum));
        $activationBytes = $this->getActivationBytes($checksum);
        $file->setResource($resource, $response->getBody()->getLength());

        $rule->setMessage(sprintf('Konvertiere %s', $file->getName()))->save();
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
    public function unload(Strategy $strategy): void
    {
        $session = $this->getSession($strategy);
        $page = $session->getPage();
        $page->clickLink('Abmelden');
        $session->stop();
    }

    public function getLockName(Rule $rule): string
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

        if (preg_match('/(.*)([\d\W]*\d)$/', $splitTitle[1], $matches) !== 1) {
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
        $cleanTitle = str_ireplace([$series, $episode], '', $cleanTitle);

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
        $cleanTitle = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '', $cleanTitle);
        $cleanTitle = preg_replace('/\s\.\s/', ' ', $cleanTitle);

        if (!empty($episode)) {
            $cleanTitle = $episode . ' ' . $cleanTitle;
        }

        return
            (empty($series) ? '' : '[' . $series . '] ') .
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
}
