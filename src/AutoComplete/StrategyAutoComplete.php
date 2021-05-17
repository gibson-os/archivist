<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\AutoComplete;

use GibsonOS\Core\AutoComplete\AutoCompleteInterface;
use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Exception\FactoryError;
use GibsonOS\Core\Exception\GetError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Service\DirService;
use GibsonOS\Core\Service\FileService;
use GibsonOS\Core\Service\ServiceManagerService;
use GibsonOS\Core\Utility\JsonUtility;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Repository\RuleRepository;
use GibsonOS\Module\Archivist\Strategy\StrategyInterface;
use JsonException;

class StrategyAutoComplete implements AutoCompleteInterface
{
    private const PARAMETER_RULE_ID = 'ruleId';

    private ServiceManagerService $serviceManagerService;

    private DirService $dirService;

    private FileService $fileService;

    private RuleRepository $ruleRepository;

    public function __construct(
        ServiceManagerService $serviceManagerService,
        DirService $dirService,
        FileService $fileService,
        RuleRepository $ruleRepository
    ) {
        $this->serviceManagerService = $serviceManagerService;
        $this->dirService = $dirService;
        $this->fileService = $fileService;
        $this->ruleRepository = $ruleRepository;
    }

    /**
     * @throws DateTimeError
     * @throws GetError
     * @throws SelectError
     * @throws JsonException
     */
    public function getByNamePart(string $namePart, array $parameters): array
    {
        $files = $this->dirService->getFiles(
            realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR .
            '..' . DIRECTORY_SEPARATOR . 'Strategy' . DIRECTORY_SEPARATOR
        );
        $namespace = 'GibsonOS\\Module\\Archivist\\Strategy\\';
        $strategies = [];
        $rule = null;

        if (!empty($parameters[self::PARAMETER_RULE_ID])) {
            $rule = $this->ruleRepository->getById((int) $parameters[self::PARAMETER_RULE_ID]);
        }

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

            if ($namePart !== '' && stripos($name, $namePart) !== 0) {
                continue;
            }

            $strategy = new Strategy($name, $namespace . $className);

            if ($rule !== null && $rule->getStrategy() === $namespace . $className) {
                $strategy->setConfig(JsonUtility::decode($rule->getConfiguration()));
            }

            $strategies[$name] = $strategy->setParameters($strategyService->getConfigurationParameters($strategy));
        }

        ksort($strategies);

        return array_values($strategies);
    }

    public function getById(string $id, array $parameters): Strategy
    {
        /** @var StrategyInterface $strategyService */
        $strategyService = $this->serviceManagerService->get($id);
        $strategy = new Strategy($strategyService->getName(), $id);

        if (!empty($parameters[self::PARAMETER_RULE_ID])) {
            $rule = $this->ruleRepository->getById((int) $parameters[self::PARAMETER_RULE_ID]);

            if ($rule->getStrategy() === $id) {
                $strategy->setConfig(JsonUtility::decode($rule->getConfiguration()));
            }
        }

        return $strategy->setParameters($strategyService->getConfigurationParameters($strategy));
    }

    public function getModel(): string
    {
        return 'GibsonOS.module.archivist.rule.model.Strategy';
    }
}
