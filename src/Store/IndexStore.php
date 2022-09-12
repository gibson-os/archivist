<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Store;

use GibsonOS\Core\Store\AbstractDatabaseStore;
use GibsonOS\Module\Archivist\Model\Index;
use GibsonOS\Module\Archivist\Model\Rule;

class IndexStore extends AbstractDatabaseStore
{
    private Rule $rule;

    protected function getModelClassName(): string
    {
        return Index::class;
    }

    protected function setWheres(): void
    {
        $this->addWhere('`rule_id`=?', [$this->rule->getId() ?? 0]);
    }

    public function setRule(Rule $rule): IndexStore
    {
        $this->rule = $rule;

        return $this;
    }
}
