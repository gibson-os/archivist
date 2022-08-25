<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Store;

use GibsonOS\Core\Store\AbstractDatabaseStore;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Model\Rule;

class RuleStore extends AbstractDatabaseStore
{
    private Account $account;

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
        $this->addWhere('`account_id`=?', [$this->account->getId()]);
    }

    public function setAccount(Account $account): RuleStore
    {
        $this->account = $account;

        return $this;
    }
}
