<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Repository;

use GibsonOS\Core\Attribute\GetTableName;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Repository\AbstractRepository;
use GibsonOS\Module\Archivist\Model\Index;
use GibsonOS\Module\Archivist\Model\Rule;
use stdClass;

class IndexRepository extends AbstractRepository
{
    public function __construct(
        #[GetTableName(Rule::class)] private string $ruleTableName,
        #[GetTableName(Index::class)] private string $indexTableName
    ) {
    }

    /**
     * @throws SelectError
     */
    public function getByInputPath(int $ruleId, string $inputPath): Index
    {
        $table = $this->getTable($this->indexTableName);
        $table
            ->appendJoinLeft(
                '`' . $this->ruleTableName . '`',
                '`' . $this->indexTableName . '`.`rule_id`=`' . $this->ruleTableName . '`.`id`'
            )
            ->setWhere(
                '`' . $this->indexTableName . '`.`input_path`=? AND ' .
                '`' . $this->indexTableName . '`.`rule_id`=?'
            )
            ->setWhereParameters([$inputPath, $ruleId])
            ->setSelectString(
                '`' . $this->indexTableName . '`.`id` AS `index_id`, ' .
                '`' . $this->indexTableName . '`.`input_path`, ' .
                '`' . $this->indexTableName . '`.`output_path`, ' .
                '`' . $this->indexTableName . '`.`size`, ' .
                '`' . $this->indexTableName . '`.`rule_id`, ' .
                '`' . $this->indexTableName . '`.`error`, ' .
                '`' . $this->indexTableName . '`.`changed`, ' .
                '`' . $this->ruleTableName . '`.`name`, ' .
                '`' . $this->ruleTableName . '`.`observed_filename`, ' .
                '`' . $this->ruleTableName . '`.`move_directory`, ' .
                '`' . $this->ruleTableName . '`.`move_filename`, ' .
                '`' . $this->ruleTableName . '`.`active`, ' .
                '`' . $this->ruleTableName . '`.`message`, ' .
                '`' . $this->ruleTableName . '`.`user_id`'
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
