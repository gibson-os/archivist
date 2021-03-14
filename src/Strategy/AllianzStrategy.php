<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;

class AllianzStrategy implements StrategyInterface
{
    public function getAuthenticationParameters(): ?array
    {
        return null;
    }

    public function authenticate(Strategy $strategy, array $parameters): void
    {
        // TODO: Implement authenticate() method.
    }

    public function get2FactorAuthenticationParameters(Strategy $strategy): ?array
    {
        return null;
    }

    public function authenticate2Factor(Strategy $strategy, array $parameters): void
    {
        // TODO: Implement authenticate2Factor() method.
    }

    public function getFiles(Strategy $strategy): array
    {
        return [];
    }

    public function setFileResource(File $file): File
    {
        return $file;
    }
}
