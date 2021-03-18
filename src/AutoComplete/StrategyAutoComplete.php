<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\AutoComplete;

use GibsonOS\Core\AutoComplete\AutoCompleteInterface;
use GibsonOS\Core\Exception\FactoryError;
use GibsonOS\Core\Service\DirService;
use GibsonOS\Core\Service\FileService;
use GibsonOS\Core\Service\ServiceManagerService;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Strategy\StrategyInterface;

class StrategyAutoComplete implements AutoCompleteInterface
{
    private ServiceManagerService $serviceManagerService;

    private DirService $dirService;

    private FileService $fileService;

    public function __construct(
        ServiceManagerService $serviceManagerService,
        DirService $dirService,
        FileService $fileService
    ) {
        $this->serviceManagerService = $serviceManagerService;
        $this->dirService = $dirService;
        $this->fileService = $fileService;
    }

    public function getByNamePart(string $namePart, array $parameters): array
    {
        $files = $this->dirService->getFiles(
            realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR .
            '..' . DIRECTORY_SEPARATOR . 'Strategy' . DIRECTORY_SEPARATOR
        );
        $namespace = 'GibsonOS\\Module\\Archivist\\Strategy\\';
        $strategies = [];

        foreach ($files as $file) {
            $className = str_replace('.php', '', $this->fileService->getFilename($file));

            try {
                $strategyService = $this->serviceManagerService->get($namespace . $className);
            } catch (FactoryError $e) {
                continue;
            }

            if (!$strategyService instanceof StrategyInterface) {
                continue;
            }

            $name = $strategyService->getName();

            if ($namePart !== '' && strpos($name, $namePart) !== 0) {
                continue;
            }

            $strategies[$name] = new Strategy($name, $namespace . $className);
        }

        ksort($strategies);

        return array_values($strategies);
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
