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
use GibsonOS\Core\Exception\WebException;
use GibsonOS\Core\Service\CryptService;
use GibsonOS\Core\Service\DateTimeService;
use GibsonOS\Core\Service\FfmpegService;
use GibsonOS\Core\Service\ProcessService;
use GibsonOS\Core\Service\WebService;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Exception\StrategyException;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Service\BrowserService;
use Psr\Log\LoggerInterface;

class AudibleStrategy extends AbstractWebStrategy
{
    private const URL = 'https://audible.de/';

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
        return [
            'email' => (new StringParameter('E-Mail'))->setInputType(StringParameter::INPUT_TYPE_EMAIL),
            'password' => (new StringParameter('Passwort'))->setInputType(StringParameter::INPUT_TYPE_PASSWORD),
            'elements' => new OptionParameter('Elemente', [
                'Einzelne Hörbücher' => 'single',
                'Serien' => 'series',
                'Podcast' => 'podcast',
            ]),
        ];
    }

    public function saveConfigurationParameters(Strategy $strategy, array $parameters): bool
    {
        $session = $this->browserService->getSession();
        $page = $this->browserService->loadPage($session, self::URL);
        $page->clickLink('Bereits Kunde? Anmelden');
        $this->browserService->waitForElementById($page, 'ap_email');
        $this->browserService->fillFormFields($page, ['email' => $parameters['email'], 'password' => $parameters['password']]);
        $page->pressButton('signInSubmit');
        $this->browserService->waitForElementById($page, 'adbl-web-carousel-c1', 60000000);
        $page->clickLink('Bibliothek');
        $this->browserService->waitForElementById($page, 'lib-subheader-actions');

        $strategy
            ->setConfigValue('session', serialize($session))
            ->setConfigValue('elements', $parameters['elements'])
            ->setConfigValue('email', $this->cryptService->encrypt($parameters['email']))
            ->setConfigValue('password', $this->cryptService->encrypt($parameters['password']))
        ;

        return true;
    }

    public function getFiles(Strategy $strategy, Rule $rule): Generator
    {
        $session = $this->getSession($strategy);
        $page = $session->getPage();

        try {
            while (true) {
                foreach ($this->getFilesFromPage($strategy) as $file) {
                    yield $file;
                }

                $page->clickLink('Eine Seite vorwärts');
            }
        } catch (ElementNotFoundException $exception) {
            // do nothing
        }
    }

    /**
     * @return Generator<File>
     */
    public function getFilesFromPage(Strategy $strategy): Generator
    {
        $expression = 'adbl-lib-action-download[^<]*<a[^<]*href="([^"]*)"[^<]*<[^<]*<[^<]*Herunterladen.+?</a>.+?';

        if ($strategy->getConfigValue('elements') === 'podcast') {
            $expression = 'adbl-episodes-link[^<]*<a[^<]*href="([^"]*)"[^<]*chevron-container.+?</a>.+?';
        }

        $session = $this->getSession($strategy);
        $page = $session->getPage();

        $pageParts = explode('class="adbl-library-content-row"', $page->getContent());

        foreach ($pageParts as $pagePart) {
            $matches = ['', '', '', '', '', '', ''];
            $expression =
                'bc-size-headline3">([^<]*).+?(Serie.+?<a[^>]*>([^<]*)</a>(, Titel (\S*))?.+?)?summaryLabel.+?' .
                $expression .
                'bc-spacing-top-base'
            ;

            if (preg_match('#' . $expression . '#s', $pagePart, $matches) !== 1) {
                continue;
            }

            $titleParts = [
                'title' => $matches[1],
                'series' => $matches[3],
                'episode' => $matches[5],
            ];

            if (empty($titleParts['series'])) {
                $this->findSeriesAndEpisode($titleParts);
            }

            if (
                empty($titleParts['series']) &&
                $strategy->getConfigValue('elements') === 'series'
            ) {
                continue;
            }

            yield new File($this->cleanTitle($titleParts), $matches[6], $this->dateTimeService->get(), $strategy);
        }
    }

    /**
     * @throws StrategyException
     * @throws DriverException
     * @throws WebException
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
//        unlink($tmpFileNameMp3);
//        unlink($tmpFileName);

        return $file;
        // https://www.voss.earth/2018/08/01/audible-dateien-$rcrackProccess
        // ffprobe.exe file.aax
        // ffmpeg -activation_bytes 9736d71d -i file.aax -map 0:a -vn file.mp3
    }

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

    private function findSeriesAndEpisode(array &$titleParts): void
    {
        $splitTitle = explode(':', $titleParts['title']);

        if (count($splitTitle) !== 2) {
            return;
        }

        $matches = ['', '', ''];

        if (preg_match('/(.+?)([\d|\W]*)$/', $splitTitle[1], $matches) !== 1) {
            return;
        }

        $titleParts['series'] = trim($matches[1]);
        $titleParts['episode'] = trim($matches[2]);
    }

    private function cleanTitle(array $titleParts): string
    {
        $cleanTitle = str_ireplace(['staffel'], '', $titleParts['title']);
        $cleanTitleParts = explode(':', $cleanTitle);

        if (
            count($cleanTitleParts) === 2 &&
            mb_strpos($cleanTitleParts[0], $titleParts['series']) !== false &&
            mb_strpos($cleanTitleParts[1], $titleParts['series']) === false
        ) {
            $cleanTitle = $cleanTitleParts[1] . ':' . $cleanTitleParts[0];
        }

        $cleanTitle = str_ireplace([$titleParts['series'], $titleParts['episode']], '', $cleanTitle);

        if (!empty($titleParts['series'])) {
            $cleanTitle = preg_replace('/:.*/s', '', $cleanTitle);
        }

        $cleanTitle = trim($cleanTitle);

        if (empty($cleanTitle)) {
            $cleanTitle = $titleParts['series'];
        }

        $cleanTitle = preg_replace('/^[\W|_]*/', '', $cleanTitle);
        $cleanTitle = preg_replace('/[\W|_]*$/', '', $cleanTitle);

        if (!empty($titleParts['episode'])) {
            $cleanTitle = $titleParts['episode'] . ' ' . $cleanTitle;
        }

        return
            (empty($titleParts['series']) ? '' : '[' . $titleParts['series'] . '] ') .
            trim(preg_replace('/\s{2,}/s', ' ', $cleanTitle))
        ;
    }

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
