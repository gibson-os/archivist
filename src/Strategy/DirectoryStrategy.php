<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Generator;
use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Exception\File\ReaderException;
use GibsonOS\Core\Exception\GetError;
use GibsonOS\Core\Exception\Lock\LockException;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Manager\ModelManager;
use GibsonOS\Core\Service\DateTimeService;
use GibsonOS\Core\Service\DirService;
use GibsonOS\Core\Service\File\ReaderService;
use GibsonOS\Core\Service\FileService;
use GibsonOS\Core\Service\LockService;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Service\RuleService;
use GibsonOS\Module\Explorer\Dto\Parameter\DirectoryParameter;
use GibsonOS\Module\Explorer\Service\TrashService;
use JsonException;
use ReflectionException;

class DirectoryStrategy implements StrategyInterface
{
    private const MAX_WAIT_SECONDS = 900;

    private const WAIT_PER_LOOP_SECONDS = 3;

    private const KEY_DIRECTORY = 'directory';

    private array $viewedFiles = [];

    private array $loadedFiles = [];

    private int $waitTime = 0;

    public function __construct(
        private readonly DirService $dirService,
        private readonly FileService $fileService,
        private readonly DateTimeService $dateTimeService,
        private readonly LockService $lockService,
        private readonly ModelManager $modelManager,
        private readonly ReaderService $readerService,
    ) {
    }

    public function getName(): string
    {
        return 'Ordner';
    }

    public function getAccountParameters(Strategy $strategy): array
    {
        return [self::KEY_DIRECTORY => new DirectoryParameter()];
    }

    public function setAccountParameters(Account $account, array $parameters): void
    {
        $configuration = $account->getConfiguration();
        $configuration[self::KEY_DIRECTORY] = $parameters[self::KEY_DIRECTORY];
        $account->setConfiguration($configuration);
    }

    public function getExecuteParameters(Account $account): array
    {
        return [];
    }

    public function setExecuteParameters(Account $account, array $parameters): bool
    {
        return true;
    }

    /**
     * @throws GetError
     * @throws JsonException
     * @throws LockException
     * @throws SaveError
     * @throws DateTimeError
     * @throws ReflectionException
     */
    public function getFiles(Account $account): Generator
    {
        $configuration = $account->getConfiguration();
        $directory = $configuration[self::KEY_DIRECTORY];

        foreach ($this->dirService->getFiles($directory) as $file) {
            $lockName = RuleService::RULE_LOCK_PREFIX . self::KEY_DIRECTORY . $directory;

            if ($this->lockService->shouldStop($lockName)) {
                return null;
            }

            if (
                is_dir($file) ||
                in_array($file, $this->viewedFiles)
            ) {
                continue;
            }

            $this->waitTime = 0;
            $this->modelManager->saveWithoutChildren($account->setMessage(sprintf('Prüfe ob Datei %s noch größer wird', $file)));
            $fileSize = filesize($file);
            sleep(1);

            if ($fileSize !== filesize($file)) {
                continue;
            }

            $this->viewedFiles[] = $file;

            try {
                $content = $this->readerService->getContent($file);
            } catch (ReaderException) {
                $content = null;
            }

            yield new File(
                $this->fileService->getFilename($file),
                $directory,
                $this->dateTimeService->get('@' . filemtime($file)),
                $account,
                $content,
            );
        }

        if (count($this->loadedFiles) === 0) {
            $this->modelManager->saveWithoutChildren($account->setMessage('Warte auf neue Dateien'));
            $this->waitTime += self::WAIT_PER_LOOP_SECONDS;
            sleep(self::WAIT_PER_LOOP_SECONDS);

            if ($this->waitTime >= self::MAX_WAIT_SECONDS) {
                return null;
            }

            yield from $this->getFiles($account);
        }
    }

    public function setFileResource(File $file, Account $account): File
    {
        $fileName = $this->dirService->addEndSlash($file->getPath()) . $file->getName();
        $file->setResource(fopen($fileName, 'r'), filesize($fileName));
        $this->loadedFiles[] = $fileName;

        return $file;
    }

    public function unload(Account $account): void
    {
        foreach ($this->loadedFiles as $file) {
            unlink($file);
//            $this->trashService->add($file);
        }

        $this->waitTime = 0;
        $this->viewedFiles = [];
        $this->loadedFiles = [];
    }

    /**
     * @throws JsonException
     * @throws SaveError
     * @throws ReflectionException
     */
    public function getLockName(Account $account): string
    {
        $lockName = self::KEY_DIRECTORY . $account->getConfiguration()[self::KEY_DIRECTORY];
        $this->lockService->stop(RuleService::RULE_LOCK_PREFIX . $lockName);

        while ($this->lockService->isLocked(RuleService::RULE_LOCK_PREFIX . $lockName)) {
            usleep(1000);
        }

        return $lockName;
    }
}
