<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Generator;
use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Exception\Model\DeleteError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Repository\LockRepository;
use GibsonOS\Core\Service\DateTimeService;
use GibsonOS\Core\Service\DirService;
use GibsonOS\Core\Service\FileService;
use GibsonOS\Core\Service\ProcessService;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Explorer\Dto\Parameter\DirectoryParameter;
use GibsonOS\Module\Explorer\Service\TrashService;

class DirectoryStrategy implements StrategyInterface
{
    private const MAX_WAIT_SECONDS = 900;

    private const WAIT_PER_LOOP_SECONDS = 3;

    private DirService $dirService;

    private FileService $fileService;

    private DateTimeService $dateTimeService;

    private TrashService $trashService;

    private LockRepository $lockRepository;

    private ProcessService $processService;

    public function __construct(
        DirService $dirService,
        FileService $fileService,
        DateTimeService $dateTimeService,
        TrashService $trashService,
        LockRepository $lockRepository,
        ProcessService $processService
    ) {
        $this->dirService = $dirService;
        $this->fileService = $fileService;
        $this->dateTimeService = $dateTimeService;
        $this->trashService = $trashService;
        $this->lockRepository = $lockRepository;
        $this->processService = $processService;
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

    public function getFiles(Strategy $strategy): Generator
    {
        $viewedFiles = $strategy->hasConfigValue('viewedFiles') ? $strategy->getConfigValue('viewedFiles') : [];
        $directory = $strategy->getConfigValue('directory');

        foreach ($this->dirService->getFiles($directory) as $file) {
            if (
                is_dir($file) ||
                in_array($file, $viewedFiles)
            ) {
                continue;
            }

            $fileSize = filesize($file);
            sleep(self::WAIT_PER_LOOP_SECONDS);
            $strategy->setConfigValue('waitTime', 0);

            if ($fileSize != filesize($file)) {
                continue;
            }

            $viewedFiles[] = $file;
            $strategy->setConfigValue('viewedFiles', $viewedFiles);

            yield new File(
                $this->fileService->getFilename($file),
                $directory,
                $this->dateTimeService->get('@' . filemtime($file)),
                $strategy
            );
        }

        $waitTime =
            ($strategy->hasConfigValue('waitTime')
                ? ((int) $strategy->getConfigValue('waitTime')) :
                0)
            + self::WAIT_PER_LOOP_SECONDS
        ;
        sleep(self::WAIT_PER_LOOP_SECONDS);

        if ($waitTime >= self::MAX_WAIT_SECONDS) {
            return null;
        }

        $strategy->setConfigValue('waitTime', $waitTime);

        foreach ($this->getFiles($strategy) as $file) {
            yield $file;
        }
    }

    public function setFileResource(File $file): File
    {
        $fileName = $this->dirService->addEndSlash($file->getPath()) . $file->getName();
        $file->setResource(fopen($fileName, 'r'), filesize($fileName));

        $files = $file->getStrategy()->hasConfigValue('loadedFiles')
            ? $file->getStrategy()->getConfigValue('loadedFiles')
            : []
        ;
        $files[] = $fileName;
        $file->getStrategy()->setConfigValue('loadedFiles', $files);

        return $file;
    }

    public function unload(Strategy $strategy): void
    {
        if (!$strategy->hasConfigValue('loadedFiles')) {
            return;
        }

        foreach ($strategy->getConfigValue('loadedFiles') as $file) {
            unlink($file);
//            $this->trashService->add($file);
        }

        $strategy->setConfigValue('loadedFiles', []);
    }

    /**
     * @throws DateTimeError
     * @throws DeleteError
     */
    public function getLockName(Rule $rule): string
    {
        $lockName = 'directory' . $rule->getId();

        try {
            $lock = $this->lockRepository->getByName('archivistIndexer' . $lockName);
            $this->processService->kill($lock->getPid());
            $lock->delete();
        } catch (SelectError $e) {
            // do nothing
        }

        return $lockName;
    }
}
