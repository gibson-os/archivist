<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Store;

use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Store\AbstractDatabaseStore;
use GibsonOS\Module\Archivist\Model\Rule;

class RuleStore extends AbstractDatabaseStore
{
    private ?int $userId = null;

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
