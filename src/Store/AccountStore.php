<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Store;

use GibsonOS\Core\Model\User;
use GibsonOS\Core\Store\AbstractDatabaseStore;
use GibsonOS\Module\Archivist\Model\Account;

/**
 * @extends AbstractDatabaseStore<Account>
 */
class AccountStore extends AbstractDatabaseStore
{
    private User $user;

    protected function getModelClassName(): string
    {
        return Account::class;
    }

    protected function setWheres(): void
    {
        $this->addWhere('`user_id`=?', [$this->user->getId()]);
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }
}
