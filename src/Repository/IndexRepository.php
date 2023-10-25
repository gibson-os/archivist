<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Repository;

use GibsonOS\Core\Dto\Model\ChildrenMapping;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Repository\AbstractRepository;
use GibsonOS\Module\Archivist\Model\Index;
use JsonException;
use MDO\Exception\ClientException;
use MDO\Exception\RecordException;
use ReflectionException;

class IndexRepository extends AbstractRepository
{
    /**
     * @throws SelectError
     * @throws JsonException
     * @throws ClientException
     * @throws RecordException
     * @throws ReflectionException
     */
    public function getByInputPath(int $ruleId, string $inputPath): Index
    {
        return $this->fetchOne(
            '`i`.`input_path`=? AND `i`.`rule_id`=?',
            [$inputPath, $ruleId],
            Index::class,
            children: [new ChildrenMapping('rule', 'rule_', 'r')],
        );
    }
}
