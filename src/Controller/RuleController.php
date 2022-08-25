<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Controller;

use GibsonOS\Core\Attribute\CheckPermission;
use GibsonOS\Core\Attribute\GetMappedModel;
use GibsonOS\Core\Attribute\GetModel;
use GibsonOS\Core\Attribute\GetModels;
use GibsonOS\Core\Controller\AbstractController;
use GibsonOS\Core\Dto\Parameter\BoolParameter;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Core\Exception\FactoryError;
use GibsonOS\Core\Exception\Model\DeleteError;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Manager\ModelManager;
use GibsonOS\Core\Manager\ServiceManager;
use GibsonOS\Core\Model\User\Permission;
use GibsonOS\Core\Service\Response\AjaxResponse;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Store\RuleStore;
use GibsonOS\Module\Archivist\Strategy\StrategyInterface;
use GibsonOS\Module\Explorer\Dto\Parameter\DirectoryParameter;
use JsonException;
use ReflectionException;

class RuleController extends AbstractController
{
    /**
     * @throws JsonException
     * @throws ReflectionException
     * @throws SelectError
     */
    #[CheckPermission(Permission::READ)]
    public function index(
        RuleStore $ruleStore,
        #[GetModel] Account $account,
        int $start = 0,
        int $limit = 100,
        array $sort = []
    ): AjaxResponse {
        $ruleStore->setAccount($account);
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
        array $configuration,
        array $parameters,
        #[GetModel] Account $account,
        #[GetModel] Rule $rule = null
    ): AjaxResponse {
        if ($rule !== null) {
            $configuration = array_merge($rule->getConfiguration(), $configuration);
        }

        /** @var StrategyInterface $strategyService */
        $strategyService = $serviceManager->get($account->getStrategy(), StrategyInterface::class);
//        $strategyDto = (new Strategy($strategyService->getName(), $strategy))
//            ->setConfiguration($configuration)
//            ->setConfigurationStep($configurationStep)
//        ;

        if (!$strategyService->setRuleParameters($rule, $parameters)) {
            $configurationParameters = $strategyService->getRuleParameters($rule);

            if (!empty($configurationParameters)) {
                if ($rule !== null) {
                    foreach ($configurationParameters as $parameterName => $configurationParameter) {
                        $configurationParameter->setValue($configuration[$parameterName]);
                    }
                }

                return $this->returnSuccess($strategyService->getRuleParameters($rule));
            }
        }

        return $this->returnSuccess([
            'parameters' => [
                'name' => (new StringParameter('Name'))
                    ->setValue($rule?->getName()),
                'observedFilename' => (new StringParameter('Beobachtete Dateinamen'))
                    ->setValue($rule?->getObservedFilename()),
                'observedContent' => (new StringParameter('Beobachteter Inhalt'))
                    ->setValue($rule?->getObservedFilename()),
                'moveDirectory' => (new DirectoryParameter('Ablage Verzeichnis'))
                    ->setValue($rule?->getMoveDirectory()),
                'moveFilename' => (new StringParameter('Ablage Dateiname'))
                    ->setValue($rule?->getMoveFilename()),
                'active' => (new BoolParameter('Aktiv'))
                    ->setValue($rule?->isActive()),
            ],
            'configuration' => $rule->getConfiguration(),
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
        #[GetMappedModel] Rule $rule
    ): AjaxResponse {
        $modelManager->save($rule);

        return $this->returnSuccess($rule);
    }

    /**
     * @param Rule[] $rules
     *
     * @throws JsonException
     * @throws DeleteError
     */
    #[CheckPermission(Permission::DELETE)]
    public function delete(
        ModelManager $modelManager,
        #[GetModels(Rule::class)] array $rules,
    ): AjaxResponse {
        foreach ($rules as $rule) {
            $modelManager->delete($rule);
        }

        return $this->returnSuccess();
    }
}
