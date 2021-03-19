<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;

class DirectoryStrategy implements StrategyInterface
{
    public function getName(): string
    {
        return 'Ordner';
    }

    public function getConfigurationParameters(Strategy $strategy): array
    {
        return [];
    }

    public function saveConfigurationParameters(Strategy $strategy, array $parameters): bool
    {
        return true;
    }

    public function get2FactorAuthenticationParameters(Strategy $strategy): array
    {
        return [];
    }

    public function authenticate2Factor(Strategy $strategy, array $parameters): void
    {
        // TODO: Implement authenticate2Factor() method.
    }

    public function getFiles(Strategy $strategy, array $parameters): array
    {
        return [];
    }

    public function setFileResource(File $file): File
    {
        return $file;
    }
}
