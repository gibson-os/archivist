<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Generator;
use GibsonOS\Core\Exception\DeleteError;
use GibsonOS\Core\Exception\File\ReaderException;
use GibsonOS\Core\Exception\FileNotFound;
use GibsonOS\Core\Exception\GetError;
use GibsonOS\Core\Service\DateTimeService;
use GibsonOS\Core\Service\DirService;
use GibsonOS\Core\Service\File\ReaderService;
use GibsonOS\Core\Service\FileService;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Explorer\Dto\Parameter\DirectoryParameter;

class DirectoryStrategy implements StrategyInterface
{
    private const KEY_DIRECTORY = 'directory';

    private array $loadedFiles = [];

    public function __construct(
        private readonly DirService $dirService,
        private readonly FileService $fileService,
        private readonly DateTimeService $dateTimeService,
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
     */
    public function getFiles(Account $account): Generator
    {
        $configuration = $account->getConfiguration();
        $directory = $configuration[self::KEY_DIRECTORY];

        foreach ($this->dirService->getFiles($directory) as $file) {
            if (is_dir($file)) {
                continue;
            }

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
    }

    public function setFileResource(File $file, Account $account): File
    {
        $fileName = $this->dirService->addEndSlash($file->getPath()) . $file->getName();
        $file->setResource(fopen($fileName, 'r'), filesize($fileName));
        $this->loadedFiles[] = $fileName;

        return $file;
    }

    /**
     * @throws GetError
     * @throws DeleteError
     * @throws FileNotFound
     */
    public function unload(Account $account): void
    {
        foreach ($this->loadedFiles as $file) {
            $this->fileService->delete($file);
        }

        $this->loadedFiles = [];
    }

    public function getLockName(Account $account): string
    {
        return self::KEY_DIRECTORY . $account->getConfiguration()[self::KEY_DIRECTORY];
    }
}
