<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Store;

use GibsonOS\Core\Attribute\GetTableName;
use GibsonOS\Core\Store\AbstractDatabaseStore;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Model\Index;
use GibsonOS\Module\Archivist\Model\Rule;
use mysqlDatabase;

class IndexStore extends AbstractDatabaseStore
{
    private ?Account $account = null;

    private ?Rule $rule = null;

    public function __construct(
        mysqlDatabase $database = null,
        #[GetTableName(Rule::class)] private readonly string $ruleTableName,
    ) {
        parent::__construct($database);
    }

    protected function getModelClassName(): string
    {
        return Index::class;
    }

    protected function initTable(): void
    {
        parent::initTable();

        if ($this->account === null) {
            return;
        }

        $this->table->appendJoin($this->ruleTableName, sprintf('`rule_id`=`%s`.`id`', $this->ruleTableName));
    }

    protected function setWheres(): void
    {
        if ($this->rule !== null) {
            $this->addWhere('`rule_id`=?', [$this->rule->getId() ?? 0]);

            return;
        }

        if ($this->account === null) {
            return;
        }

        $this->addWhere(sprintf('`%s`.`account_id`=?', $this->ruleTableName), [$this->account->getId() ?? 0]);
    }

    public function setAccount(?Account $account): IndexStore
    {
        $this->account = $account;

        return $this;
    }

    public function setRule(?Rule $rule): IndexStore
    {
        $this->rule = $rule;

        return $this;
    }
}
