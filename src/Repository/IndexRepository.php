<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Repository;

use DateTimeImmutable;
use Exception;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Repository\AbstractRepository;
use GibsonOS\Module\Archivist\Model\Index;
use GibsonOS\Module\Archivist\Model\Rule;
use stdClass;

class IndexRepository extends AbstractRepository
{
    /**
     * @throws SelectError
     * @throws Exception
     */
    public function getByInputPath(string $inputPath): Index
    {
        $table = $this->getTable(Index::getTableName());
        $table
            ->appendJoinLeft(
                '`' . Rule::getTableName() . '`',
                '`' . Index::getTableName() . '`.`rule_id`=`' . Rule::getTableName() . '`.`id`'
            )
            ->setWhere('`' . Index::getTableName() . '`.`input_path`=?')
            ->addWhereParameter($inputPath)
            ->setSelectString(
                '`' . Index::getTableName() . '`.`id` AS `index_id`, ' .
                '`' . Index::getTableName() . '`.`input_path`, ' .
                '`' . Index::getTableName() . '`.`output_path`, ' .
                '`' . Index::getTableName() . '`.`size`, ' .
                '`' . Index::getTableName() . '`.`rule_id`, ' .
                '`' . Index::getTableName() . '`.`changed`, ' .
                '`' . Rule::getTableName() . '`.`name`, ' .
                '`' . Rule::getTableName() . '`.`observed_directory`, ' .
                '`' . Rule::getTableName() . '`.`observed_filename`, ' .
                '`' . Rule::getTableName() . '`.`move_directory`, ' .
                '`' . Rule::getTableName() . '`.`move_filename`, ' .
                '`' . Rule::getTableName() . '`.`active`, ' .
                '`' . Rule::getTableName() . '`.`count`, ' .
                '`' . Rule::getTableName() . '`.`user_id`'
            )
        ;

        if (!$table->selectPrepared(false)) {
            throw (new SelectError())->setTable($table);
        }

        $record = $table->connection->fetchObject();

        if (!$record instanceof stdClass) {
            throw (new SelectError())->setTable($table);
        }

        $model = (new Index())
            ->setId((int) $record->index_id)
            ->setInputPath($record->input_path)
            ->setOutputPath($record->output_path)
            ->setSize((int) $record->size)
            ->setChanged(new DateTimeImmutable($record->changed))
        ;

        if (!empty($record->rule_id)) {
            $model->setRule(
                (new Rule())
                    ->setId($record->rule_id)
                    ->setName($record->name)
                    ->setObservedDirectory($record->observe_directory)
                    ->setObservedFilename($record->observe_filename)
                    ->setMoveDirectory($record->move_directory)
                    ->setMoveFilename($record->move_filename)
                    ->setActive((bool) $record->active)
                    ->setCount((int) $record->count)
                    ->setUserId((int) $record->user_id)
            );
        }

        return $model;
    }
}
