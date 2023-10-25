<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Store;

use GibsonOS\Core\Attribute\GetTableName;
use GibsonOS\Core\Store\AbstractDatabaseStore;
use GibsonOS\Core\Wrapper\DatabaseStoreWrapper;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Model\Index;
use GibsonOS\Module\Archivist\Model\Rule;
use MDO\Dto\Query\Join;
use MDO\Enum\OrderDirection;
use MDO\Exception\ClientException;
use ReflectionException;

class IndexStore extends AbstractDatabaseStore
{
    private ?Account $account = null;

    private ?Rule $rule = null;

    public function __construct(
        DatabaseStoreWrapper $databaseStoreWrapper,
        #[GetTableName(Rule::class)]
        private readonly string $ruleTableName,
    ) {
        parent::__construct($databaseStoreWrapper);
    }

    protected function getModelClassName(): string
    {
        return Index::class;
    }

    protected function getAlias(): ?string
    {
        return 'i';
    }

    /**
     * @throws ClientException
     * @throws ReflectionException
     */
    protected function initQuery(): void
    {
        parent::initQuery();

        if ($this->account === null) {
            return;
        }

        $this->selectQuery->addJoin(
            new Join($this->getTable($this->ruleTableName), 'r', '`i`.`rule_id`=`r`.`id`')
        );
    }

    protected function setWheres(): void
    {
        if ($this->rule !== null) {
            $this->addWhere('`i`.`rule_id`=?', [$this->rule->getId() ?? 0]);

            return;
        }

        if ($this->account === null) {
            return;
        }

        $this->addWhere('`r`.`account_id`=?', [$this->account->getId() ?? 0]);
    }

    public function setAccount(?Account $account): IndexStore
    {
        $this->account = $account;

        return $this;
    }

    public function setRule(?Rule $rule): IndexStore
    {
        $this->rule = $rule;

        return $this;
    }

    protected function getDefaultOrder(): string
    {
        return '`i`.`changed`';
    }

    protected function getDefaultOrderDirection(): OrderDirection
    {
        return OrderDirection::DESC;
    }

    protected function getOrderMapping(): array
    {
        return [
            'inputPath' => '`input_path`',
            'outputPath' => '`output_path`',
            'size' => '`size`',
            'error' => '`error`',
            'changed' => '`changed`',
        ];
    }
}
