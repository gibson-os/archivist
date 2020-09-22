<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Repository;

use Generator;
use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Repository\AbstractRepository;
use GibsonOS\Module\Archivist\Model\Rule;

class RuleRepository extends AbstractRepository
{
    /**
     * @throws SelectError
     * @throws DateTimeError
     *
     * @return Generator<Rule>|Rule[]
     */
    public function getAll(): iterable
    {
        $table = $this->getTable(Rule::getTableName());
        $table->setOrderBy('`active` DESC');

        if (!$table->select()) {
            throw (new SelectError())->setTable($table);
        }

        do {
            $model = new Rule();
            $model->loadFromMysqlTable($table);

            yield $model;
        } while ($table->next());
    }

    public function hasActive(string $observeDirectory, string $observeFilename = null): bool
    {
        $table = $this->getTable(Rule::getTableName());
        $table
            ->setWhere(
                '`observe_directory`=?' .
                ($observeFilename === null ? '' : ' AND `observe_filename`=?') .
                '`active`=1'
            )
            ->addParameter($observeDirectory)
        ;

        if ($observeFilename !== null) {
            $table->addParameter($observeFilename);
        }

        return (bool) $table->selectPrepared(false);
    }
}
