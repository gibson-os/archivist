<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\AutoComplete;

use GibsonOS\Core\AutoComplete\AutoCompleteInterface;
use GibsonOS\Core\Exception\AutoCompleteException;
use GibsonOS\Core\Model\ModelInterface;
use GibsonOS\Core\Service\ServiceManagerService;
use GibsonOS\Module\Archivist\Model\Strategy;
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

    public function getById($id, array $parameters): ModelInterface
    {
        /** @var StrategyInterface $strategy */
        $strategy = $this->serviceManagerService->get($id);

        return (new Strategy())
            ->setId($id)
            ->setName($strategy->getName())
        ;
    }

    public function getModel(): string
    {
        return 'GibsonOS.module.archivist.rule.model.Strategy';
    }

    /**
     * @throws AutoCompleteException
     */
    public function getIdFromModel(ModelInterface $model)
    {
        throw new AutoCompleteException('Strategy has no model!');
    }
}
