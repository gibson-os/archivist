<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Repository;

use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Repository\AbstractRepository;
use GibsonOS\Module\Archivist\Model\Index;
use GibsonOS\Module\Archivist\Model\Rule;
use mysqlTable;
use stdClass;

/**
 * @method Index getModel(mysqlTable $table, string $modelClassName)
 */
class IndexRepository extends AbstractRepository
{
    /**
     * @throws SelectError
     */
    public function getByInputPath(int $ruleId, string $inputPath): Index
    {
        $table = $this->getTable(Index::getTableName());
        $table
            ->appendJoinLeft(
                '`' . Rule::getTableName() . '`',
                '`' . Index::getTableName() . '`.`rule_id`=`' . Rule::getTableName() . '`.`id`'
            )
            ->setWhere(
                '`' . Index::getTableName() . '`.`input_path`=? AND ' .
                '`' . Index::getTableName() . '`.`rule_id`=?'
            )
            ->setWhereParameters([$inputPath, $ruleId])
            ->setSelectString(
                '`' . Index::getTableName() . '`.`id` AS `index_id`, ' .
                '`' . Index::getTableName() . '`.`input_path`, ' .
                '`' . Index::getTableName() . '`.`output_path`, ' .
                '`' . Index::getTableName() . '`.`size`, ' .
                '`' . Index::getTableName() . '`.`rule_id`, ' .
                '`' . Index::getTableName() . '`.`error`, ' .
                '`' . Index::getTableName() . '`.`changed`, ' .
                '`' . Rule::getTableName() . '`.`name`, ' .
                '`' . Rule::getTableName() . '`.`observed_filename`, ' .
                '`' . Rule::getTableName() . '`.`move_directory`, ' .
                '`' . Rule::getTableName() . '`.`move_filename`, ' .
                '`' . Rule::getTableName() . '`.`active`, ' .
                '`' . Rule::getTableName() . '`.`message`, ' .
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

        $model = $this->getModel($table, Index::class);

        if (!empty($record->rule_id)) {
            $model->setRule(
                (new Rule())
                    ->setId($record->rule_id)
                    ->setName($record->name)
                    ->setObservedFilename($record->observed_filename)
                    ->setMoveDirectory($record->move_directory)
                    ->setMoveFilename($record->move_filename)
                    ->setActive((bool) $record->active)
                    ->setMessage($record->message)
                    ->setUserId((int) $record->user_id)
            );
        }

        return $model;
    }
}
