<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use GibsonOS\Core\Service\DateTimeService;
use GibsonOS\Core\Service\DirService;
use GibsonOS\Core\Service\FileService;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Explorer\Dto\Parameter\DirectoryParameter;

class DirectoryStrategy implements StrategyInterface
{
    private DirService $dirService;

    private FileService $fileService;

    private DateTimeService $dateTimeService;

    public function __construct(DirService $dirService, FileService $fileService, DateTimeService $dateTimeService)
    {
        $this->dirService = $dirService;
        $this->fileService = $fileService;
        $this->dateTimeService = $dateTimeService;
    }

    public function getName(): string
    {
        return 'Ordner';
    }

    public function getConfigurationParameters(Strategy $strategy): array
    {
        return ['directory' => new DirectoryParameter()];
    }

    public function saveConfigurationParameters(Strategy $strategy, array $parameters): bool
    {
        $strategy->setConfigValue('directory', $parameters['directory']);

        return true;
    }

    public function getFiles(Strategy $strategy): array
    {
        $files = [];
        $directory = $strategy->getConfigValue('directory');

        foreach ($this->dirService->getFiles($directory) as $file) {
            if (is_dir($file)) {
                continue;
            }

            $files[] = new File(
                $this->fileService->getFilename($file),
                $directory,
                $this->dateTimeService->get('@' . filemtime($file)),
                $strategy
            );
        }

        return $files;
    }

    public function setFileResource(File $file): File
    {
        $fileName = $this->dirService->addEndSlash($file->getPath()) . $file->getName();
        $file->setResource(fopen($fileName, 'r'), filesize($fileName));

        return $file;
    }
}
