<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Generator;
use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Exception\Flock\LockError;
use GibsonOS\Core\Exception\GetError;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Service\DateTimeService;
use GibsonOS\Core\Service\DirService;
use GibsonOS\Core\Service\FileService;
use GibsonOS\Core\Service\LockService;
use GibsonOS\Core\Utility\JsonUtility;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Service\RuleService;
use GibsonOS\Module\Explorer\Dto\Parameter\DirectoryParameter;
use GibsonOS\Module\Explorer\Service\TrashService;
use JsonException;

class DirectoryStrategy implements StrategyInterface
{
    private const MAX_WAIT_SECONDS = 900;

    private const WAIT_PER_LOOP_SECONDS = 3;

    private DirService $dirService;

    private FileService $fileService;

    private DateTimeService $dateTimeService;

    private TrashService $trashService;

    private LockService $lockService;

    public function __construct(
        DirService $dirService,
        FileService $fileService,
        DateTimeService $dateTimeService,
        TrashService $trashService,
        LockService $lockService
    ) {
        $this->dirService = $dirService;
        $this->fileService = $fileService;
        $this->dateTimeService = $dateTimeService;
        $this->trashService = $trashService;
        $this->lockService = $lockService;
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

    /**
     * @throws GetError
     * @throws JsonException
     * @throws LockError
     * @throws SaveError
     * @throws DateTimeError
     */
    public function getFiles(Strategy $strategy, Rule $rule): Generator
    {
        $viewedFiles = $strategy->hasConfigValue('viewedFiles') ? $strategy->getConfigValue('viewedFiles') : [];
        $directory = $strategy->getConfigValue('directory');

        foreach ($this->dirService->getFiles($directory) as $file) {
            $lockName =
                RuleService::RULE_LOCK_PREFIX . 'directory' .
                JsonUtility::decode($rule->getConfiguration())['directory']
            ;

            if ($this->lockService->shouldStop($lockName)) {
                return null;
            }

            if (
                is_dir($file) ||
                in_array($file, $viewedFiles)
            ) {
                continue;
            }

            $strategy->setConfigValue('waitTime', 0);
            $rule->setMessage(sprintf('Prüfe ob Datei %s noch größer wird', $file))->save();
            $fileSize = filesize($file);
            sleep(1);

            if ($fileSize !== filesize($file)) {
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

        if (!$strategy->hasConfigValue('loadedFiles')) {
            $rule->setMessage('Warte auf neue Dateien')->save();
            $waitTime =
                ($strategy->hasConfigValue('waitTime')
                    ? ((int) $strategy->getConfigValue('waitTime')) :
                    0)
                + self::WAIT_PER_LOOP_SECONDS;
            sleep(self::WAIT_PER_LOOP_SECONDS);

            if ($waitTime >= self::MAX_WAIT_SECONDS) {
                return null;
            }

            $strategy->setConfigValue('waitTime', $waitTime);

            yield from $this->getFiles($strategy, $rule);
        }
    }

    public function setFileResource(File $file, Rule $rule): File
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
     * @throws JsonException
     * @throws SaveError
     */
    public function getLockName(Rule $rule): string
    {
        $lockName = 'directory' . JsonUtility::decode($rule->getConfiguration())['directory'];
        $this->lockService->stop(RuleService::RULE_LOCK_PREFIX . $lockName);

        while ($this->lockService->isLocked(RuleService::RULE_LOCK_PREFIX . $lockName)) {
            usleep(1000);
        }

        return $lockName;
    }
}
