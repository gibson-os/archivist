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
            return [];
        }

        do {
            $model = new Rule();
            $model->loadFromMysqlTable($table);

            yield $model;
        } while ($table->next());
    }

    public function hasActive(string $observedDirectory, string $observedFilename = null): bool
    {
        $table = $this->getTable(Rule::getTableName());
        $table
            ->setWhere(
                '`observed_directory`=? AND ' .
                '`active`=1' .
                ($observedFilename === null ? '' : ' AND `observed_filename`=?')
            )
            ->addParameter($observedDirectory)
        ;

        if ($observedFilename !== null) {
            $table->addParameter($observedFilename);
        }

        return (bool) $table->selectPrepared(false);
    }
}
