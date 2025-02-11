<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Command;

use GibsonOS\Core\Attribute\Command\Argument;
use GibsonOS\Core\Command\AbstractCommand;
use GibsonOS\Core\Exception\CreateError;
use GibsonOS\Core\Exception\FactoryError;
use GibsonOS\Core\Exception\Lock\LockException;
use GibsonOS\Core\Exception\Lock\UnlockException;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Module\Archivist\Exception\RuleException;
use GibsonOS\Module\Archivist\Repository\AccountRepository;
use GibsonOS\Module\Archivist\Service\AccountService;
use JsonException;
use Psr\Log\LoggerInterface;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @description Index new founded files by rules
 */
class IndexerCommand extends AbstractCommand
{
    #[Argument('Account ID to ru rules')]
    private int $accountId;

    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly AccountService $accountService,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    /**
     * @throws CreateError
     * @throws FactoryError
     * @throws JsonException
     * @throws LoaderError
     * @throws LockException
     * @throws RuleException
     * @throws RuntimeError
     * @throws SaveError
     * @throws SelectError
     * @throws SyntaxError
     * @throws Throwable
     * @throws UnlockException
     */
    protected function run(): int
    {
        return $this->accountService->execute($this->accountRepository->getById($this->accountId))
            ? self::SUCCESS
            : self::ERROR
        ;
    }

    public function setAccountId(int $accountId): void
    {
        $this->accountId = $accountId;
    }
}
