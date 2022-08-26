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
use GibsonOS\Module\Archivist\Model\Account;
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

    public function setAccountParameters(Account $account, array $parameters): void
    {
        $configuration = $account->getConfiguration();
        $configuration['directory'] = $parameters['directory'];
        $account->setConfiguration($configuration);
    }

    public function getRuleParameters(Account $account, Rule $rule = null): array
    {
        return [];
    }

    public function setRuleParameters(Rule $rule, array $parameters): void
    {
    }

    public function getExecuteParameters(Account $account): array
    {
        return [];
    }

    public function setExecuteParameters(Account $account, int $step, array $parameters): bool
    {
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
    public function getFiles(Account $account, Rule $rule): Generator
    {
        $configuration = $account->getConfiguration();
        $executionParameters = $account->getExecutionParameters();
        $viewedFiles = $configuration['viewedFiles'] ?? [];
        $directory = $configuration['directory'];

        foreach ($this->dirService->getFiles($directory) as $file) {
            $lockName = RuleService::RULE_LOCK_PREFIX . 'directory' . $configuration['directory'];

            if ($this->lockService->shouldStop($lockName)) {
                return null;
            }

            if (
                is_dir($file) ||
                in_array($file, $viewedFiles)
            ) {
                continue;
            }

            $configuration['waitTime'] = 0;
            $this->modelManager->save($account->setMessage(sprintf('Prüfe ob Datei %s noch größer wird', $file)));
            $fileSize = filesize($file);
            sleep(1);

            if ($fileSize !== filesize($file)) {
                continue;
            }

            $viewedFiles[] = $file;
            $configuration['viewedFiles'] = $viewedFiles;
            $account->setConfiguration($configuration);

            yield new File(
                $this->fileService->getFilename($file),
                $directory,
                $this->dateTimeService->get('@' . filemtime($file)),
                $account
            );
        }

        if (count($executionParameters['loadedFiled'] ?? []) === 0) {
            $this->modelManager->save($account->setMessage('Warte auf neue Dateien'));
            $waitTime = ($executionParameters['waitTime'] ?? 0) + self::WAIT_PER_LOOP_SECONDS;
            sleep(self::WAIT_PER_LOOP_SECONDS);

            if ($waitTime >= self::MAX_WAIT_SECONDS) {
                return null;
            }

            $executionParameters['waitTime'] = $waitTime;
            $account->setExecutionParameters($executionParameters);

            yield from $this->getFiles($account, $rule);
        }
    }

    public function setFileResource(File $file, Account $account): File
    {
        $fileName = $this->dirService->addEndSlash($file->getPath()) . $file->getName();
        $file->setResource(fopen($fileName, 'r'), filesize($fileName));
        $executionParameters = $file->getAccount()->getExecutionParameters();
        $files = $executionParameters['loadedFiles'] ?? [];
        $files[] = $fileName;
        $executionParameters['loadedFiles'] = $files;
        $file->getAccount()->setExecutionParameters($executionParameters);

        return $file;
    }

    public function unload(Account $account): void
    {
        $configuration = $account->getConfiguration();

        if (!isset($configuration['loadedFiles'])) {
            return;
        }

        foreach ($configuration['loadedFiles'] as $file) {
            unlink($file);
//            $this->trashService->add($file);
        }

        $configuration['loadedFiles'] = [];
        $account->setConfiguration($configuration);
    }

    /**
     * @throws JsonException
     * @throws SaveError
     * @throws ReflectionException
     */
    public function getLockName(Account $account): string
    {
        $lockName = 'directory' . $account->getConfiguration()['directory'];
        $this->lockService->stop(RuleService::RULE_LOCK_PREFIX . $lockName);

        while ($this->lockService->isLocked(RuleService::RULE_LOCK_PREFIX . $lockName)) {
            usleep(1000);
        }

        return $lockName;
    }
}
