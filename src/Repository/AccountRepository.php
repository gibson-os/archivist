<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Repository;

use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Repository\AbstractRepository;
use GibsonOS\Module\Archivist\Model\Account;

class AccountRepository extends AbstractRepository
{
    /**
     * @throws SelectError
     */
    public function getById(int $id): Account
    {
        return $this->fetchOne('`id`=?', [$id], Account::class);
    }
}
