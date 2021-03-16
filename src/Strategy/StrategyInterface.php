<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use GibsonOS\Core\Dto\Parameter\AbstractParameter;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;

interface StrategyInterface
{
    public function getName(): string;

    /**
     * @return AbstractParameter[]|null
     */
    public function getAuthenticationParameters(): ?array;

    /**
     * @param array<string, string> $parameters
     */
    public function authenticate(Strategy $strategy, array $parameters): void;

    /**
     * @return AbstractParameter[]|null
     */
    public function get2FactorAuthenticationParameters(Strategy $strategy): ?array;

    /**
     * @param array<string, string> $parameters
     */
    public function authenticate2Factor(Strategy $strategy, array $parameters): void;

    /**
     * @return File[]
     */
    public function getFiles(Strategy $strategy): array;

    public function setFileResource(File $file): File;
}
