<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Command;

use GibsonOS\Core\Command\AbstractCommand;
use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Exception\Flock\LockError;
use GibsonOS\Core\Exception\Flock\UnlockError;
use GibsonOS\Core\Exception\GetError;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Service\DirService;
use GibsonOS\Core\Service\LockService;
use GibsonOS\Module\Archivist\Repository\IndexRepository;
use GibsonOS\Module\Archivist\Repository\RuleRepository;
use GibsonOS\Module\Archivist\Service\RuleService;
use Psr\Log\LoggerInterface;

class IndexerCommand extends AbstractCommand
{
    private const LOCK_NAME = 'archivistIndexer';

    private RuleRepository $ruleRepository;

    private DirService $dirService;

    private IndexRepository $indexRepository;

    private RuleService $ruleService;

    private LockService $lockService;

    public function __construct(
        RuleRepository $ruleRepository,
        IndexRepository $indexRepository,
        DirService $dirService,
        RuleService $ruleService,
        LockService $lockService,
        LoggerInterface $logger
    ) {
        $this->ruleRepository = $ruleRepository;
        $this->dirService = $dirService;
        $this->indexRepository = $indexRepository;
        $this->ruleService = $ruleService;
        $this->lockService = $lockService;

        parent::__construct($logger);
    }

    /**
     * @throws DateTimeError
     * @throws GetError
     * @throws SaveError
     * @throws SelectError
     * @throws UnlockError
     */
    protected function run(): int
    {
        try {
            $this->lockService->lock(self::LOCK_NAME);
            $scannedDirectories = [];

            foreach ($this->ruleRepository->getAll() as $rule) {
//                $directory = $this->dirService->addEndSlash($rule->getObservedDirectory());
//
//                if (isset($scannedDirectories[$directory . $rule->getObservedFilename()])) {
//                    continue;
//                }

                $this->ruleService->indexFiles($rule);
            }

            $this->lockService->unlock(self::LOCK_NAME);
        } catch (LockError $e) {
            // Indexer in progress
        }

        return 0;
    }
}
