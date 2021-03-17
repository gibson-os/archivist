<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\AutoComplete;

use GibsonOS\Core\AutoComplete\AutoCompleteInterface;
use GibsonOS\Core\Service\ServiceManagerService;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Strategy\StrategyInterface;

class StrategyAutoComplete implements AutoCompleteInterface
{
    private ServiceManagerService $serviceManagerService;

    public function __construct(ServiceManagerService $serviceManagerService)
    {
        $this->serviceManagerService = $serviceManagerService;
    }

    public function getByNamePart(string $namePart, array $parameters): array
    {
        // TODO: Implement getByNamePart() method.
        return [];
    }

    public function getById($id, array $parameters): Strategy
    {
        /** @var StrategyInterface $strategy */
        $strategy = $this->serviceManagerService->get($id);

        return new Strategy($strategy->getName(), $id);
    }

    public function getModel(): string
    {
        return 'GibsonOS.module.archivist.rule.model.Strategy';
    }
}
