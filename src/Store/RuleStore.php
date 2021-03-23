<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Store;

use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Service\ServiceManagerService;
use GibsonOS\Core\Store\AbstractDatabaseStore;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Strategy\StrategyInterface;
use mysqlDatabase;

class RuleStore extends AbstractDatabaseStore
{
    private ?int $userId = null;

    private ServiceManagerService $serviceManagerService;

    public function __construct(ServiceManagerService $serviceManagerService, mysqlDatabase $database = null)
    {
        parent::__construct($database);
        $this->serviceManagerService = $serviceManagerService;
    }

    protected function getTableName(): string
    {
        return Rule::getTableName();
    }

    protected function getCountField(): string
    {
        return '`id`';
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

    /**
     * @throws DateTimeError
     *
     * @return Rule[]
     */
    public function getList(): array
    {
        $this->table->setOrderBy($this->getOrderBy());

        if ($this->userId !== null) {
            $this->table
                ->setWhere('`user_id`=?')
                ->addWhereParameter($this->userId)
            ;
        }

        if (!$this->table->selectPrepared()) {
            return [];
        }

        $rules = [];

        do {
            $rule = new Rule();
            $rule->loadFromMysqlTable($this->table);
            /** @var StrategyInterface $ruleStrategy */
            $ruleStrategy = $this->serviceManagerService->get($rule->getStrategy(), StrategyInterface::class);
            $rule->setStrategy($ruleStrategy->getName());
            $rules[] = $rule;
        } while ($this->table->next());

        return $rules;
    }

    public function setUserId(?int $userId): RuleStore
    {
        $this->userId = $userId;

        return $this;
    }
}
