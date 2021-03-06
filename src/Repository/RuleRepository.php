<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Repository;

use GibsonOS\Core\Attribute\GetTableName;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Repository\AbstractRepository;
use GibsonOS\Module\Archivist\Model\Rule;

class RuleRepository extends AbstractRepository
{
    public function __construct(#[GetTableName(Rule::class)] private string $ruleTableName)
    {
    }

    /**
     * @throws SelectError
     */
    public function getById(int $id): Rule
    {
        return $this->fetchOne('`id`=?', [$id], Rule::class);
    }

    /**
     * @throws SelectError
     */
    public function getAll(): array
    {
        return $this->fetchAll('', [], Rule::class, null, null, '`active` DESC');
    }

    public function hasActive(string $observedDirectory, string $observedFilename = null): bool
    {
        $table = $this->getTable($this->ruleTableName);
        $table
            ->setWhere(
                '`observed_directory`=? AND `active`=?' .
                ($observedFilename === null ? '' : ' AND `observed_filename`=?')
            )
            ->setWhereParameters([$observedDirectory, 1])
        ;

        if ($observedFilename !== null) {
            $table->addWhereParameter($observedFilename);
        }

        return (bool) $table->selectPrepared(false);
    }

    /**
     * @param int[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        $table = $this->getTable($this->ruleTableName);

        $table
            ->setWhere('`id` IN (' . implode(', ', array_map(fn ($id) => '?', $ids)) . ')')
            ->setWhereParameters($ids)
            ->deletePrepared()
        ;
    }
}
