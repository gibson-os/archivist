<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Store;

use GibsonOS\Core\Store\AbstractDatabaseStore;
use GibsonOS\Module\Archivist\Model\Rule;

class RuleStore extends AbstractDatabaseStore
{
    /**
     * @var int|null
     */
    private $userId;

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
            'observeDirector' => '`observer_directory`',
            'observeFilename' => '`observer_filename`',
            'moveDirectory' => '`move_directory`',
            'moveFilename' => '`move_filename`',
        ];
    }

    public function getList(): array
    {
        $this->table->setOrderBy($this->getOrderBy());

        if ($this->userId !== null) {
            $this->table
                ->setWhere('`user_id`=?')
                ->addParameter($this->userId)
            ;
        }

        $this->table->select(
            false,
            '`id`, ' .
            '`name`, ' .
            '`observe_directory` AS `observeDirectory`, ' .
            '`observe_filename` AS `observeFilename`, ' .
            '`move_directory` AS `moveDirectory`, ' .
            '`move_filename` AS `moveFilename`, ' .
            '`active` ' .
            '`count`'
        );

        return $this->table->connection->fetchAssocList();
    }

    public function setUserId(?int $userId): RuleStore
    {
        $this->userId = $userId;

        return $this;
    }
}
