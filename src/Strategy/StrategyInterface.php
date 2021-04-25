<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Generator;
use GibsonOS\Core\Dto\Parameter\AbstractParameter;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Model\Rule;

interface StrategyInterface
{
    public function getName(): string;

    /**
     * @return AbstractParameter[]
     */
    public function getConfigurationParameters(Strategy $strategy): array;

    /**
     * @param array<string, string> $parameters
     */
    public function saveConfigurationParameters(Strategy $strategy, array $parameters): bool;

    /**
     * @return Generator<File>
     */
    public function getFiles(Strategy $strategy, Rule $rule = null): Generator;

    public function setFileResource(File $file): File;

    public function unload(Strategy $strategy): void;

    public function getLockName(Rule $rule): string;
}
