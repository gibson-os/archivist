<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Service;

use GibsonOS\Core\Exception\CreateError;
use GibsonOS\Core\Exception\FactoryError;
use GibsonOS\Core\Exception\Lock\LockException;
use GibsonOS\Core\Exception\Lock\UnlockException;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Manager\ModelManager;
use GibsonOS\Core\Manager\ServiceManager;
use GibsonOS\Core\Service\LockService;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Exception\RuleException;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Strategy\StrategyInterface;
use JsonException;
use Psr\Log\LoggerInterface;
use ReflectionException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class AccountService
{
    public const RULE_LOCK_PREFIX = 'archivistIndexer';

    public function __construct(
        private readonly ServiceManager $serviceManager,
        private readonly LoggerInterface $logger,
        private readonly LockService $lockService,
        private readonly ModelManager $modelManager,
        private readonly RuleService $ruleService,
    ) {
    }

    /**
     * @throws FactoryError
     * @throws JsonException
     * @throws RuleException
     * @throws CreateError
     * @throws LockException
     * @throws UnlockException
     * @throws SaveError
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws ReflectionException
     */
    public function execute(Account $account): bool
    {
        $this->logger->info(sprintf('Start indexing for account %s', $account->getName()));
        /** @var StrategyInterface $strategy */
        $strategy = $this->serviceManager->get($account->getStrategy(), StrategyInterface::class);

        $lockName = self::RULE_LOCK_PREFIX . $strategy->getLockName($account);
        $this->lockService->lock($lockName);

        $rules = array_filter(
            $account->getRules(),
            fn (Rule $rule): bool => $rule->isActive()
        );

        if (count($rules) === 0) {
            $this->logger->warning(sprintf('No active rules for account %s!', $account->getName()));
            $this->modelManager->saveWithoutChildren($account->setMessage('Keine aktiven Regeln vorhanden'));

            return false;
        }

        $this->modelManager->saveWithoutChildren($account->setMessage('Ermittel Dateien'));
        $this->logger->info(sprintf(
            'Get files with %s strategy for account %s',
            $strategy->getName(),
            $account->getName()
        ));

        foreach ($strategy->getFiles($account) as $file) {
            if (!$file instanceof File) {
                continue;
            }

            foreach ($rules as $rule) {
                $this->ruleService->execute($rule, $file, $strategy);
            }
        }

        $strategy->unload($account);
        $account->setExecutionParameters([]);
        $this->modelManager->saveWithoutChildren($account->setMessage('Fertig'));
        $this->lockService->unlock($lockName);

        return true;
    }
}
