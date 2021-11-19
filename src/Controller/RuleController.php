<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Controller;

use GibsonOS\Core\Attribute\CheckPermission;
use GibsonOS\Core\Controller\AbstractController;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Core\Exception\FactoryError;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Model\User\Permission;
use GibsonOS\Core\Service\CommandService;
use GibsonOS\Core\Service\Response\AjaxResponse;
use GibsonOS\Core\Service\ServiceManagerService;
use GibsonOS\Core\Utility\JsonUtility;
use GibsonOS\Module\Archivist\Command\IndexerCommand;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Repository\RuleRepository;
use GibsonOS\Module\Archivist\Store\RuleStore;
use GibsonOS\Module\Archivist\Strategy\StrategyInterface;
use GibsonOS\Module\Explorer\Dto\Parameter\DirectoryParameter;
use JsonException;

class RuleController extends AbstractController
{
    /**
     * @throws FactoryError
     */
    #[CheckPermission(Permission::READ)]
    public function index(RuleStore $ruleStore, int $start = 0, int $limit = 100, array $sort = []): AjaxResponse
    {
        $ruleStore->setLimit($limit, $start);
        $ruleStore->setSortByExt($sort);

        return $this->returnSuccess($ruleStore->getList(), $ruleStore->getCount());
    }

    /**
     * @param class-string $strategy
     *
     * @throws FactoryError
     * @throws JsonException
     * @throws SelectError
     */
    #[CheckPermission(Permission::WRITE)]
    public function edit(
        ServiceManagerService $serviceManagerService,
        RuleRepository $ruleRepository,
        string $strategy,
        array $configuration,
        array $parameters,
        int $configurationStep = 0,
        int $id = null
    ): AjaxResponse {
        $rule = null;

        if ($id !== null) {
            $rule = $ruleRepository->getById($id);
            $configuration = array_merge(JsonUtility::decode($rule->getConfiguration()), $configuration);
        }

        /** @var StrategyInterface $strategyService */
        $strategyService = $serviceManagerService->get($strategy, StrategyInterface::class);
        $strategyDto = (new Strategy($strategyService->getName(), $strategy))
            ->setConfiguration($configuration)
            ->setConfigurationStep($configurationStep)
        ;

        if (!$strategyService->saveConfigurationParameters($strategyDto, $parameters)) {
            $configurationParameters = $strategyService->getConfigurationParameters($strategyDto);

            if (!empty($configurationParameters)) {
                if ($rule !== null) {
                    foreach ($configurationParameters as $parameterName => $configurationParameter) {
                        $configurationParameter->setValue($configuration[$parameterName]);
                    }
                }

                return $this->returnSuccess($strategyDto->setParameters($configurationParameters));
            }
        }

        return $this->returnSuccess([
            'parameters' => [
                'name' => (new StringParameter('Name'))
                    ->setValue($rule?->getName()),
                'observedFilename' => (new StringParameter('Beobachtungsregel'))
                    ->setValue($rule?->getObservedFilename()),
                'moveDirectory' => (new DirectoryParameter('Ablage Verzeichnis'))
                    ->setValue($rule?->getMoveDirectory()),
                'moveFilename' => (new StringParameter('Ablage Dateiname'))
                    ->setValue($rule?->getMoveFilename()),
            ],
            'configuration' => $strategyDto->getConfiguration(),
            'className' => $strategy,
            'lastStep' => true,
            'id' => $rule?->getId(),
        ]);
    }

    /**
     * @param class-string $strategy
     *
     * @throws JsonException
     * @throws SaveError
     * @throws SelectError
     */
    #[CheckPermission(Permission::WRITE)]
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
        $rule = new Rule();

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
            ->save()
        ;

        return $this->returnSuccess($rule);
    }

    /**
     * @param int[] $ruleIds
     */
    #[CheckPermission(Permission::DELETE)]
    public function delete(RuleRepository $ruleRepository, array $ruleIds): AjaxResponse
    {
        $ruleRepository->deleteByIds($ruleIds);

        return $this->returnSuccess();
    }

    /**
     * @param class-string $strategy
     *
     * @throws JsonException
     * @throws SaveError
     * @throws SelectError
     */
    #[CheckPermission(Permission::WRITE)]
    public function execute(
        RuleRepository $ruleRepository,
        CommandService $commandService,
        string $strategy,
        array $configuration,
        string $name,
        string $observedFilename,
        string $moveDirectory,
        string $moveFilename,
        int $id
    ): AjaxResponse {
        $rule = $ruleRepository->getById($id)->setConfiguration(JsonUtility::encode($configuration));
        $rule
            ->setActive(true)
            ->setMessage('Starte')
            ->setName($name)
            ->setStrategy($strategy)
            ->setConfiguration(JsonUtility::encode($configuration))
            ->setObservedFilename($observedFilename)
            ->setMoveDirectory($moveDirectory)
            ->setMoveFilename($moveFilename)
            ->save()
        ;
        $commandService->executeAsync(IndexerCommand::class, ['ruleId' => $id]);

        return $this->returnSuccess($rule);
    }

    /**
     * @throws SelectError
     */
    #[CheckPermission(Permission::READ)]
    public function status(RuleRepository $ruleRepository, int $id): AjaxResponse
    {
        return $this->returnSuccess($ruleRepository->getById($id));
    }
}
