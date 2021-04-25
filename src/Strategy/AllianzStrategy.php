<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Generator;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Model\Rule;

class AllianzStrategy implements StrategyInterface
{
    public function getName(): string
    {
        return 'Allianz';
    }

    public function getConfigurationParameters(Strategy $strategy): array
    {
        return [];
    }

    public function saveConfigurationParameters(Strategy $strategy, array $parameters): bool
    {
        return true;
    }

    public function getFiles(Strategy $strategy): Generator
    {
        yield null;
    }

    public function setFileResource(File $file): File
    {
        return $file;
    }

    public function unload(Strategy $strategy): void
    {
    }

    public function getLockName(Rule $rule): string
    {
        return 'allianz';
    }
}
