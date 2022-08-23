<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Controller;

use GibsonOS\Core\Attribute\CheckPermission;
use GibsonOS\Core\Attribute\GetMappedModel;
use GibsonOS\Core\Attribute\GetModel;
use GibsonOS\Core\Controller\AbstractController;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Core\Exception\FactoryError;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Manager\ModelManager;
use GibsonOS\Core\Manager\ServiceManager;
use GibsonOS\Core\Model\User\Permission;
use GibsonOS\Core\Service\Response\AjaxResponse;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Repository\RuleRepository;
use GibsonOS\Module\Archivist\Store\RuleStore;
use GibsonOS\Module\Archivist\Strategy\StrategyInterface;
use GibsonOS\Module\Explorer\Dto\Parameter\DirectoryParameter;
use JsonException;
use ReflectionException;

class RuleController extends AbstractController
{
    /**
     * @throws FactoryError
     * @throws SelectError
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
     */
    #[CheckPermission(Permission::WRITE)]
    public function edit(
        ServiceManager $serviceManager,
        string $strategy,
        array $configuration,
        array $parameters,
        int $configurationStep = 0,
        #[GetModel] Rule $rule = null
    ): AjaxResponse {
        if ($rule !== null) {
            $configuration = array_merge($rule->getConfiguration(), $configuration);
        }

        /** @var StrategyInterface $strategyService */
        $strategyService = $serviceManager->get($strategy, StrategyInterface::class);
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
     * @throws JsonException
     * @throws SaveError
     * @throws ReflectionException
     */
    #[CheckPermission(Permission::WRITE)]
    public function save(
        ModelManager $modelManager,
        #[GetMappedModel(['user_id' => 'session.user.id'], ['user' => 'session.user'])] Rule $rule
    ): AjaxResponse {
        $modelManager->save($rule);

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
}
