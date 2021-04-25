<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Command;

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
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class IndexerCommand extends AbstractCommand
{
    private RuleRepository $ruleRepository;

    private RuleService $ruleService;

    public function __construct(
        RuleRepository $ruleRepository,
        RuleService $ruleService,
        LoggerInterface $logger
    ) {
        $this->ruleRepository = $ruleRepository;
        $this->ruleService = $ruleService;

        parent::__construct($logger);

        $this->setArgument('ruleId', true);
    }

    /**
     * @throws ArgumentError
     * @throws DateTimeError
     * @throws FactoryError
     * @throws JsonException
     * @throws SelectError
     * @throws UnlockError
     * @throws CreateError
     * @throws SaveError
     * @throws RuleException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    protected function run(): int
    {
        $ruleId = (int) $this->getArgument('ruleId');
        $rule = $this->ruleRepository->getById($ruleId);

        try {
            $this->ruleService->executeRule($rule);
        } catch (LockError $e) {
            $rule->setMessage('Eine Indexierung für diese Strategy läuft schon')->save();
        }

        return 0;
    }
}
