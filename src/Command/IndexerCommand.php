<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Command;

use GibsonOS\Core\Command\AbstractCommand;
use GibsonOS\Core\Exception\ArgumentError;
use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Exception\FactoryError;
use GibsonOS\Core\Exception\Flock\LockError;
use GibsonOS\Core\Exception\Flock\UnlockError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Service\LockService;
use GibsonOS\Module\Archivist\Repository\RuleRepository;
use GibsonOS\Module\Archivist\Service\RuleService;
use JsonException;
use Psr\Log\LoggerInterface;

class IndexerCommand extends AbstractCommand
{
    private const LOCK_NAME = 'archivistIndexer';

    private RuleRepository $ruleRepository;

    private RuleService $ruleService;

    private LockService $lockService;

    public function __construct(
        RuleRepository $ruleRepository,
        RuleService $ruleService,
        LockService $lockService,
        LoggerInterface $logger
    ) {
        $this->ruleRepository = $ruleRepository;
        $this->ruleService = $ruleService;
        $this->lockService = $lockService;

        parent::__construct($logger);

        $this->setArgument('ruleId', true);
    }

    /**
     * @throws DateTimeError
     * @throws SelectError
     * @throws UnlockError
     * @throws ArgumentError
     * @throws FactoryError
     * @throws JsonException
     */
    protected function run(): int
    {
        $ruleId = (int) $this->getArgument('ruleId');

        try {
            $this->lockService->lock(self::LOCK_NAME . $ruleId);
            $this->ruleService->executeRule($this->ruleRepository->getById($ruleId));
            $this->lockService->unlock(self::LOCK_NAME . $ruleId);
        } catch (LockError $e) {
            // Indexer in progress
        }

        return 0;
    }
}
