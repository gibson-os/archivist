<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;

class CommerzbankStrategy implements StrategyInterface
{
    public function getName(): string
    {
        return 'Commerzbank';
    }

    public function getConfigurationParameters(Strategy $strategy): array
    {
        return [];
    }

    public function saveConfigurationParameters(Strategy $strategy, array $parameters): bool
    {
        return true;
    }

    public function getFiles(Strategy $strategy): array
    {
        return [];
    }

    public function setFileResource(File $file): File
    {
        return $file;
    }

    public function unload(Strategy $strategy): void
    {
    }
}
