<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Command;

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
use GibsonOS\Module\Archivist\Exception\RuleException;
use GibsonOS\Module\Archivist\Repository\RuleRepository;
use GibsonOS\Module\Archivist\Service\RuleService;
use JsonException;
use Psr\Log\LoggerInterface;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[Cronjob]
class IndexerCommand extends AbstractCommand
{
    public function __construct(
        private RuleRepository $ruleRepository,
        private RuleService $ruleService,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);

        $this->setArgument('ruleId', true);
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
        $ruleId = (int) $this->getArgument('ruleId');
        $rule = $this->ruleRepository->getById($ruleId);

        try {
            $this->ruleService->executeRule($rule);
        } catch (LockError) {
            $this->logger->warning('Indexing for this strategy already runs!');
            $rule
                ->setActive(false)
                ->setMessage('Eine Indexierung für diese Strategy läuft bereits')
                ->save()
            ;
        } catch (Throwable $exception) {
            $rule
                ->setActive(false)
                ->setMessage(sprintf('Exception: %s', $exception->getMessage()))
                ->save()
            ;

            throw $exception;
        }

        return 0;
    }
}
