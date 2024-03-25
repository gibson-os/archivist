<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Generator;
use GibsonOS\Core\Dto\Ffmpeg\Media;
use GibsonOS\Core\Dto\Ffmpeg\Stream\Audio;
use GibsonOS\Core\Dto\Parameter\AbstractParameter;
use GibsonOS\Core\Dto\Parameter\TextParameter;
use GibsonOS\Core\Dto\Web\Request;
use GibsonOS\Core\Exception\DeleteError;
use GibsonOS\Core\Exception\FfmpegException;
use GibsonOS\Core\Exception\File\OpenError;
use GibsonOS\Core\Exception\FileNotFound;
use GibsonOS\Core\Exception\GetError;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\ProcessError;
use GibsonOS\Core\Exception\WebException;
use GibsonOS\Core\Manager\ModelManager;
use GibsonOS\Core\Service\CryptService;
use GibsonOS\Core\Service\FfmpegService;
use GibsonOS\Core\Service\FileService;
use GibsonOS\Core\Service\ProcessService;
use GibsonOS\Core\Service\WebService;
use GibsonOS\Module\Archivist\Collector\AudibleFileCollector;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Exception\StrategyException;
use GibsonOS\Module\Archivist\Factory\Request\AudibleRequestFactory;
use GibsonOS\Module\Archivist\Model\Account;
use JsonException;
use MDO\Exception\RecordException;
use Psr\Log\LoggerInterface;
use ReflectionException;

class AudibleStrategy implements StrategyInterface
{
    public const URL = 'https://www.audible.de';

    private const KEY_COOKIES_JAR = 'cookiesJar';

    private const KEY_LOGIN = 'login';

    public function __construct(
        private readonly WebService $webService,
        private readonly LoggerInterface $logger,
        private readonly CryptService $cryptService,
        private readonly ModelManager $modelManager,
        private readonly FfmpegService $ffmpegService,
        private readonly ProcessService $processService,
        private readonly AudibleFileCollector $audibleFileCollector,
        private readonly AudibleRequestFactory $audibleRequestFactory,
        private readonly FileService $fileService,
    ) {
    }

    public function getName(): string
    {
        return 'Audible';
    }

    public function getAccountParameters(Strategy $strategy): array
    {
        return [self::KEY_COOKIES_JAR => (new TextParameter('Cookie jar Datei'))];
    }

    public function setAccountParameters(Account $account, array $parameters): void
    {
        $configuration = $account->getConfiguration();
        $configuration[self::KEY_COOKIES_JAR] = $this->cryptService->encrypt(
            $parameters[self::KEY_COOKIES_JAR] ?? $this->cryptService->decrypt($configuration[self::KEY_COOKIES_JAR]),
        );
        $account->setConfiguration($configuration);
    }

    /**
     * @return AbstractParameter[]
     */
    public function getExecuteParameters(Account $account): array
    {
        if ($account->getExecutionParameters()[self::KEY_LOGIN] ?? false) {
            return [];
        }

        return [self::KEY_COOKIES_JAR => (new TextParameter('Cookies jar Datei'))];
    }

    /**
     * @throws WebException
     */
    public function setExecuteParameters(Account $account, array $parameters): bool
    {
        $configuration = $account->getConfiguration();
        $cookiesJar = $parameters[self::KEY_COOKIES_JAR] ?? null;
        $account->setExecutionParameters([self::KEY_LOGIN => false]);

        if ($cookiesJar === null && isset($configuration[self::KEY_COOKIES_JAR])) {
            $cookiesJar = $this->cryptService->decrypt($configuration[self::KEY_COOKIES_JAR]);
        }

        if ($cookiesJar === null) {
            return false;
        }

        $account->setConfiguration([self::KEY_COOKIES_JAR => $this->cryptService->encrypt($cookiesJar)]);
        $response = $this->webService->get($this->getRequest($account, sprintf('%s/library', self::URL)));

        if (preg_match('#>\s*Bibliothek\s*</h1>#', $response->getBody()->getContent()) !== 1) {
            return false;
        }

        $account->setExecutionParameters([self::KEY_LOGIN => true]);

        return true;
    }

    /**
     * @throws JsonException
     * @throws RecordException
     * @throws ReflectionException
     * @throws SaveError
     * @throws WebException
     */
    public function getFiles(Account $account): Generator
    {
        $response = $this->webService->get($this->getRequest($account, sprintf('%s/library', self::URL)));
        $page = 1;
        $content = $response->getBody()->getContent();
        $lastPage = $this->getLastPage($content);

        while ($page <= $lastPage) {
            $this->modelManager->saveWithoutChildren($account->setMessage(sprintf('Bibliothek Seite %d', $page)));
            $response = $this->webService->get(
                $this->getRequest($account, sprintf('%s/library?page=%d', self::URL, $page)),
            );
            $content = $response->getBody()->getContent();

            yield from $this->audibleFileCollector->getFilesFromPage($account, $content);

            ++$page;
        }
    }

    /**
     * @throws DeleteError
     * @throws FfmpegException
     * @throws FileNotFound
     * @throws GetError
     * @throws JsonException
     * @throws ProcessError
     * @throws RecordException
     * @throws ReflectionException
     * @throws SaveError
     * @throws StrategyException
     * @throws WebException
     * @throws OpenError
     */
    public function setFileResource(File $file, Account $account): File
    {
        if ($account->getStrategy() !== self::class) {
            throw new StrategyException(sprintf(
                'Class name %s is not equal with %s',
                $account->getStrategy(),
                self::class,
            ));
        }

        $response = $this->webService->get($this->getRequest($account, html_entity_decode($file->getPath())));
        $resource = $response->getBody()->getResource();

        if ($resource === null) {
            throw new StrategyException('File is empty!');
        }

        $tmpFileName = sprintf('%s%s%s.aax', sys_get_temp_dir(), DIRECTORY_SEPARATOR, uniqid('audible'));
        $tmpFileNameMp3 = sprintf('%s%s%s.mp3', sys_get_temp_dir(), DIRECTORY_SEPARATOR, uniqid('audible'));

        $newFile = $this->fileService->open($tmpFileName, 'w');
        stream_copy_to_stream($resource, $newFile);
        $this->fileService->close($newFile);

        $this->modelManager->saveWithoutChildren($account->setMessage(sprintf('Ermittel Checksumme für %s', $file->getName())));
        $this->logger->info(sprintf('Get checksum for %s', $file->getName()));
        $checksum = $this->ffmpegService->getChecksum($tmpFileName);

        $this->modelManager->saveWithoutChildren($account->setMessage(sprintf(
            'Ermittel Activation Bytes mit Checksumme %s für %s',
            $checksum,
            $file->getName(),
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
            ['activation_bytes' => $activationBytes],
        );
        $mp3File = $this->fileService->open($tmpFileNameMp3, 'r');
        $file->setResource($mp3File, filesize($tmpFileNameMp3));
        unlink($tmpFileNameMp3);
        unlink($tmpFileName);

        return $file;
    }

    public function unload(Account $account): void
    {
    }

    public function getLockName(Account $account): string
    {
        return 'audible';
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
            'inAudible-NG-tables' . DIRECTORY_SEPARATOR,
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

    private function getRequest(Account $account, string $url): Request
    {
        $configuration = $account->getConfiguration();
        $cookiesJar = $this->cryptService->decrypt($configuration[self::KEY_COOKIES_JAR] ?? '');

        return $this->audibleRequestFactory->getRequest($url, $cookiesJar);
    }

    private function getLastPage(string $content): int
    {
        preg_match_all('/refinementFormLink[^>]*>(\d*)/', $content, $matches);

        return (int) end($matches[1]);

    }
}
