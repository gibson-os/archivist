<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Generator;
use GibsonOS\Core\Dto\Parameter\AbstractParameter;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Model\Rule;

interface StrategyInterface
{
    public function getName(): string;

    /**
     * @return AbstractParameter[]
     */
    public function getAccountParameters(Strategy $strategy): array;

    /**
     * @param array<string, string> $parameters
     */
    public function setAccountParameters(Account $account, array $parameters): void;

    /**
     * @return AbstractParameter[]
     */
    public function getRuleParameters(Rule $rule): array;

    /**
     * @param array<string, string> $parameters
     */
    public function setRuleParameters(Rule $rule, array $parameters): void;

    /**
     * @return AbstractParameter[]
     */
    public function getExecuteParameters(Account $account): array;

    /**
     * @param array<string, string> $parameters
     */
    public function setExecuteParameters(Account $account, int $step, array $parameters): bool;

    /**
     * @return Generator<File>
     */
    public function getFiles(Account $account, Rule $rule): Generator;

    public function setFileResource(File $file, Account $account): File;

    public function unload(Account $account): void;

    public function getLockName(Account $account): string;
}
