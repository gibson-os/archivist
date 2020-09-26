<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Command;

use GibsonOS\Core\Command\AbstractCommand;
use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Exception\Flock\LockError;
use GibsonOS\Core\Exception\GetError;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Service\DirService;
use GibsonOS\Core\Service\LockService;
use GibsonOS\Module\Archivist\Repository\IndexRepository;
use GibsonOS\Module\Archivist\Repository\RuleRepository;
use GibsonOS\Module\Archivist\Service\RuleService;

class IndexerCommand extends AbstractCommand
{
    private const LOCK_NAME = 'archivistIndexer';

    /**
     * @var RuleRepository
     */
    private $ruleRepository;

    /**
     * @var DirService
     */
    private $dirService;

    /**
     * @var IndexRepository
     */
    private $indexRepository;

    /**
     * @var RuleService
     */
    private $ruleService;

    /**
     * @var LockService
     */
    private $lockService;

    public function __construct(
        RuleRepository $ruleRepository,
        IndexRepository $indexRepository,
        DirService $dirService,
        RuleService $ruleService,
        LockService $lockService
    ) {
        $this->ruleRepository = $ruleRepository;
        $this->dirService = $dirService;
        $this->indexRepository = $indexRepository;
        $this->ruleService = $ruleService;
        $this->lockService = $lockService;
    }

    /**
     * @throws DateTimeError
     * @throws GetError
     * @throws SelectError
     * @throws SaveError
     */
    protected function run(): int
    {
        try {
            $this->lockService->lock(self::LOCK_NAME);
            $scannedDirectories = [];

            foreach ($this->ruleRepository->getAll() as $rule) {
                $directory = $this->dirService->addEndSlash($rule->getObservedDirectory());

                if (isset($scannedDirectories[$directory . $rule->getObservedFilename()])) {
                    continue;
                }

                $this->ruleService->indexFiles($rule);
            }

            $this->lockService->unlock(self::LOCK_NAME);
        } catch (LockError $e) {
            // Indexer in progress
        }

        return 0;
    }
}
