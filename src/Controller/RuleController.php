<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Controller;

use GibsonOS\Core\Controller\AbstractController;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Exception\FactoryError;
use GibsonOS\Core\Exception\GetError;
use GibsonOS\Core\Exception\LoginRequired;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\PermissionDenied;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Service\PermissionService;
use GibsonOS\Core\Service\Response\AjaxResponse;
use GibsonOS\Core\Service\ServiceManagerService;
use GibsonOS\Core\Utility\JsonUtility;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Repository\RuleRepository;
use GibsonOS\Module\Archivist\Service\RuleService;
use GibsonOS\Module\Archivist\Store\RuleStore;
use GibsonOS\Module\Archivist\Strategy\StrategyInterface;
use GibsonOS\Module\Explorer\Dto\Parameter\DirectoryParameter;
use JsonException;

class RuleController extends AbstractController
{
    /**
     * @throws GetError
     * @throws LoginRequired
     * @throws PermissionDenied
     */
    public function index(RuleStore $ruleStore, int $start = 0, int $limit = 100, array $sort = []): AjaxResponse
    {
        $this->checkPermission(PermissionService::READ);

        $ruleStore->setLimit($limit, $start);
        $ruleStore->setSortByExt($sort);

        return $this->returnSuccess($ruleStore->getList(), $ruleStore->getCount());
    }

    /**
     * @throws FactoryError
     * @throws LoginRequired
     * @throws PermissionDenied
     */
    public function edit(
        ServiceManagerService $serviceManagerService,
        RuleRepository $ruleRepository,
        string $strategy,
        array $configuration,
        array $parameters,
        int $id = null
    ): AjaxResponse {
        $this->checkPermission(PermissionService::WRITE);
        $rule = null;

        if ($id !== null) {
            $rule = $ruleRepository->getById($id);
            $configuration = array_merge(JsonUtility::decode($rule->getConfiguration()), $configuration);
        }

        /** @var StrategyInterface $strategyService */
        $strategyService = $serviceManagerService->get($strategy, StrategyInterface::class);
        $strategyDto = (new Strategy($strategyService->getName(), $strategy))->setConfig($configuration);

        if (!$strategyService->saveConfigurationParameters($strategyDto, $parameters)) {
            $configurationParameters = $strategyService->getConfigurationParameters($strategyDto);

            if ($rule !== null) {
                foreach ($configurationParameters as $parameterName => $configurationParameter) {
                    $configurationParameter->setValue($configuration[$parameterName]);
                }
            }

            return $this->returnSuccess(
                $strategyDto->setParameters($configurationParameters)
            );
        }

        return $this->returnSuccess([
            'parameters' => [
                'name' => (new StringParameter('Name'))
                    ->setValue($rule === null ? null : $rule->getName()),
                'observedFilename' => (new StringParameter('Beobachtungsregel'))
                    ->setValue($rule === null ? null : $rule->getObservedFilename()),
                'moveDirectory' => (new DirectoryParameter('Ablage Verzeichnis'))
                    ->setValue($rule === null ? null : $rule->getMoveDirectory()),
                'moveFilename' => (new StringParameter('Ablage Dateiname'))
                    ->setValue($rule === null ? null : $rule->getMoveFilename()),
            ],
            'files' => $strategyService->getFiles($strategyDto),
            'config' => $strategyDto->getConfig(),
            'strategy' => $strategy,
            'id' => $rule === null ? null : $rule->getId(),
        ]);
    }

    /**
     * @throws DateTimeError
     * @throws JsonException
     * @throws LoginRequired
     * @throws PermissionDenied
     * @throws SaveError
     */
    public function save(
        RuleRepository $ruleRepository,
        string $strategy,
        array $configuration,
        string $name,
        string $observedFilename,
        string $moveDirectory,
        string $moveFilename,
        int $id = null
    ): AjaxResponse {
        $this->checkPermission(PermissionService::WRITE);

        $rule = (new Rule());

        if ($id !== null) {
            $rule = $ruleRepository->getById($id);
        }

        $rule
            ->setId($id)
            ->setName($name)
            ->setStrategy($strategy)
            ->setConfiguration(JsonUtility::encode($configuration))
            ->setObservedFilename($observedFilename)
            ->setMoveDirectory($moveDirectory)
            ->setMoveFilename($moveFilename)
            ->setUserId($this->sessionService->getUserId() ?? 0)
        ;
        $rule->save();

        return $this->returnSuccess($rule);
    }

    /**
     * @param int[] $ruleIds
     */
    public function delete(RuleRepository $ruleRepository, array $ruleIds): AjaxResponse
    {
        $ruleRepository->deleteByIds($ruleIds);

        return $this->returnSuccess();
    }

    /**
     * @throws DateTimeError
     * @throws FactoryError
     * @throws JsonException
     * @throws LoginRequired
     * @throws PermissionDenied
     * @throws SelectError
     */
    public function execute(
        RuleService $ruleService,
        RuleRepository $ruleRepository,
        int $id
    ): AjaxResponse {
        $this->checkPermission(PermissionService::WRITE);

        $ruleService->executeRule($ruleRepository->getById($id));

        return $this->returnSuccess();
    }
}
