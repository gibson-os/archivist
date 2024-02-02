<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Repository;

use GibsonOS\Core\Attribute\GetTableName;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Repository\AbstractRepository;
use GibsonOS\Core\Wrapper\RepositoryWrapper;
use GibsonOS\Module\Archivist\Model\Rule;
use JsonException;
use MDO\Dto\Query\Where;
use MDO\Enum\OrderDirection;
use MDO\Exception\ClientException;
use MDO\Exception\RecordException;
use MDO\Query\DeleteQuery;
use ReflectionException;

class RuleRepository extends AbstractRepository
{
    public function __construct(
        RepositoryWrapper $repositoryWrapper,
        #[GetTableName(Rule::class)]
        private readonly string $ruleTableName,
    ) {
        parent::__construct($repositoryWrapper);
    }

    /**
     * @throws ClientException
     * @throws JsonException
     * @throws RecordException
     * @throws ReflectionException
     * @throws SelectError
     */
    public function getById(int $id): Rule
    {
        return $this->fetchOne('`id`=?', [$id], Rule::class);
    }

    /**
     * @throws JsonException
     * @throws ClientException
     * @throws RecordException
     * @throws ReflectionException
     */
    public function getAll(): array
    {
        return $this->fetchAll('1', [], Rule::class, orderBy: ['`active`' => OrderDirection::DESC]);
    }

    public function hasActive(string $observedDirectory, ?string $observedFilename = null): bool
    {
        $where = '`observed_directory`=? AND `active`=?';
        $parameters = [$observedDirectory, 1];

        if ($observedFilename !== null) {
            $where .= ' AND `observed_filename`=?';
            $parameters[] = $observedFilename;
        }

        $aggregations = $this->getAggregations(['count' => 'COUNT(`id`)'], Rule::class, $where, $parameters);

        return $aggregations->get('count')->getValue() > 0;
    }

    /**
     * @param int[] $ids
     */
    public function deleteByIds(array $ids): bool
    {
        $deleteQuery = (new DeleteQuery($this->getTable($this->ruleTableName)))
            ->addWhere(new Where(
                sprintf(
                    '`id` IN (%s)',
                    $this->getRepositoryWrapper()->getSelectService()->getParametersString($ids),
                ),
                [$ids],
            ))
        ;

        try {
            $this->getRepositoryWrapper()->getClient()->execute($deleteQuery);
        } catch (ClientException) {
            return false;
        }

        return true;
    }
}
