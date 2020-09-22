<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Repository;

use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Repository\AbstractRepository;
use GibsonOS\Module\Archivist\Model\Index;

class IndexRepository extends AbstractRepository
{
    /**
     * @throws SelectError
     * @throws DateTimeError
     */
    public function getByInputPath(string $inputPath): Index
    {
        $table = $this->getTable(Index::getTableName());
        $table
            ->setWhere('`input_path`=?')
            ->addParameter($inputPath)
        ;

        if (!$table->selectPrepared()) {
            throw (new SelectError())->setTable($table);
        }

        $model = new Index();
        $model->loadFromMysqlTable($table);

        return $model;
    }
}
