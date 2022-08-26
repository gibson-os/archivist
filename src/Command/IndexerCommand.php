<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Command;

use GibsonOS\Core\Attribute\Command\Argument;
use GibsonOS\Core\Attribute\Install\Cronjob;
use GibsonOS\Core\Command\AbstractCommand;
use GibsonOS\Core\Exception\ArgumentError;
use GibsonOS\Core\Exception\CreateError;
use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Exception\FactoryError;
use GibsonOS\Core\Exception\Flock\LockError;
use GibsonOS\Core\Exception\Flock\UnlockError;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Manager\ModelManager;
use GibsonOS\Module\Archivist\Exception\RuleException;
use GibsonOS\Module\Archivist\Repository\AccountRepository;
use GibsonOS\Module\Archivist\Service\RuleService;
use JsonException;
use Psr\Log\LoggerInterface;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @description Index new founded files by rules
 */
#[Cronjob(user: 'root')]
class IndexerCommand extends AbstractCommand
{
    #[Argument('Account ID to ru rules')]
    private int $accountId;

    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly RuleService $ruleService,
        private readonly ModelManager $modelManager,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * @throws ArgumentError
     * @throws CreateError
     * @throws DateTimeError
     * @throws FactoryError
     * @throws JsonException
     * @throws LoaderError
     * @throws LockError
     * @throws RuleException
     * @throws RuntimeError
     * @throws SaveError
     * @throws SelectError
     * @throws SyntaxError
     * @throws Throwable
     * @throws UnlockError
     */
    protected function run(): int
    {
        $account = $this->accountRepository->getById($this->accountId);

        try {
            $this->ruleService->executeRule($rule);
        } catch (LockError) {
            $this->logger->warning('Indexing for this account already runs!');
            $this->modelManager->save(
                $account
                    ->setActive(false)
                    ->setMessage('Eine Indexierung für diesen Account läuft bereits')
            );
        } catch (Throwable $exception) {
            $this->modelManager->save(
                $account
                    ->setActive(false)
                    ->setMessage(sprintf('Exception: %s', $exception->getMessage()))
            );

            throw $exception;
        }

        return self::SUCCESS;
    }

    public function setAccountId(int $accountId): void
    {
        $this->accountId = $accountId;
    }
}
