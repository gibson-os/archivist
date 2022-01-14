<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Store;

use GibsonOS\Core\Exception\FactoryError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Service\AttributeService;
use GibsonOS\Core\Service\ServiceManagerService;
use GibsonOS\Core\Store\AbstractDatabaseStore;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Strategy\StrategyInterface;
use mysqlDatabase;

class RuleStore extends AbstractDatabaseStore
{
    private ?int $userId = null;

    public function __construct(
        private ServiceManagerService $serviceManagerService,
        AttributeService $attributeService,
        mysqlDatabase $database = null
    ) {
        parent::__construct($attributeService, $database);
    }

    protected function getModelClassName(): string
    {
        return Rule::class;
    }

    protected function getOrderMapping(): array
    {
        return [
            'name' => '`name`',
            'observedDirector' => '`observed_directory`',
            'observedFilename' => '`observed_filename`',
            'moveDirectory' => '`move_directory`',
            'moveFilename' => '`move_filename`',
        ];
    }

    protected function setWheres(): void
    {
        if ($this->userId !== null) {
            $this->addWhere('`user_id`=?', [$this->userId]);
        }
    }

    /**
     * @throws FactoryError
     * @throws SelectError
     *
     * @return Rule[]|iterable
     */
    public function getList(): iterable
    {
        /** @var Rule $rule */
        foreach (parent::getList() as $rule) {
            /** @var StrategyInterface $strategyService */
            $strategyService = $this->serviceManagerService->get($rule->getStrategy());
            $rule->setStrategyByClass($strategyService);

            yield $rule;
        }
    }

    public function setUserId(?int $userId): RuleStore
    {
        $this->userId = $userId;

        return $this;
    }
}
