<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Generator;
use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Exception\Flock\LockError;
use GibsonOS\Core\Exception\GetError;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Manager\ModelManager;
use GibsonOS\Core\Service\DateTimeService;
use GibsonOS\Core\Service\DirService;
use GibsonOS\Core\Service\FileService;
use GibsonOS\Core\Service\LockService;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Service\RuleService;
use GibsonOS\Module\Explorer\Dto\Parameter\DirectoryParameter;
use GibsonOS\Module\Explorer\Service\TrashService;
use JsonException;
use ReflectionException;

class DirectoryStrategy implements StrategyInterface
{
    private const MAX_WAIT_SECONDS = 900;

    private const WAIT_PER_LOOP_SECONDS = 3;

    public function __construct(
        private readonly DirService $dirService,
        private readonly FileService $fileService,
        private readonly DateTimeService $dateTimeService,
        private readonly LockService $lockService,
        private readonly ModelManager $modelManager
    ) {
    }

    public function getName(): string
    {
        return 'Ordner';
    }

    public function getAccountParameters(Strategy $strategy): array
    {
        return ['directory' => new DirectoryParameter()];
    }

    public function getRuleParameters(Strategy $strategy): array
    {
        return [];
    }

    public function setAccountParameters(Strategy $strategy, array $parameters): bool
    {
        $strategy->setConfigurationValue('directory', $parameters['directory']);

        return true;
    }

    /**
     * @throws GetError
     * @throws JsonException
     * @throws LockError
     * @throws SaveError
     * @throws DateTimeError
     * @throws ReflectionException
     */
    public function getFiles(Strategy $strategy, Rule $rule): Generator
    {
        $viewedFiles = $strategy->hasConfigurationValue('viewedFiles') ? $strategy->getConfigurationValue('viewedFiles') : [];
        $directory = $strategy->getConfigurationValue('directory');

        foreach ($this->dirService->getFiles($directory) as $file) {
            $lockName =
                RuleService::RULE_LOCK_PREFIX . 'directory' .
                $rule->getConfiguration()['directory']
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

            $strategy->setConfigurationValue('waitTime', 0);
            $this->modelManager->save($rule->setMessage(sprintf('Prüfe ob Datei %s noch größer wird', $file)));
            $fileSize = filesize($file);
            sleep(1);

            if ($fileSize !== filesize($file)) {
                continue;
            }

            $viewedFiles[] = $file;
            $strategy->setConfigurationValue('viewedFiles', $viewedFiles);

            yield new File(
                $this->fileService->getFilename($file),
                $directory,
                $this->dateTimeService->get('@' . filemtime($file)),
                $strategy
            );
        }

        if (!$strategy->hasConfigurationValue('loadedFiles')) {
            $this->modelManager->save($rule->setMessage('Warte auf neue Dateien'));
            $waitTime =
                ($strategy->hasConfigurationValue('waitTime')
                    ? ((int) $strategy->getConfigurationValue('waitTime')) :
                    0)
                + self::WAIT_PER_LOOP_SECONDS;
            sleep(self::WAIT_PER_LOOP_SECONDS);

            if ($waitTime >= self::MAX_WAIT_SECONDS) {
                return null;
            }

            $strategy->setConfigurationValue('waitTime', $waitTime);

            yield from $this->getFiles($strategy, $rule);
        }
    }

    public function setFileResource(File $file, Rule $rule): File
    {
        $fileName = $this->dirService->addEndSlash($file->getPath()) . $file->getName();
        $file->setResource(fopen($fileName, 'r'), filesize($fileName));

        $files = $file->getStrategy()->hasConfigurationValue('loadedFiles')
            ? $file->getStrategy()->getConfigurationValue('loadedFiles')
            : []
        ;
        $files[] = $fileName;
        $file->getStrategy()->setConfigurationValue('loadedFiles', $files);

        return $file;
    }

    public function unload(Strategy $strategy): void
    {
        if (!$strategy->hasConfigurationValue('loadedFiles')) {
            return;
        }

        foreach ($strategy->getConfigurationValue('loadedFiles') as $file) {
            unlink($file);
//            $this->trashService->add($file);
        }

        $strategy->setConfigurationValue('loadedFiles', []);
    }

    /**
     * @throws JsonException
     * @throws SaveError
     * @throws ReflectionException
     */
    public function getLockName(Rule $rule): string
    {
        $lockName = 'directory' . $rule->getConfiguration()['directory'];
        $this->lockService->stop(RuleService::RULE_LOCK_PREFIX . $lockName);

        while ($this->lockService->isLocked(RuleService::RULE_LOCK_PREFIX . $lockName)) {
            usleep(1000);
        }

        return $lockName;
    }
}
