<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Controller;

use GibsonOS\Core\Attribute\CheckPermission;
use GibsonOS\Core\Attribute\GetMappedModel;
use GibsonOS\Core\Attribute\GetMappedModels;
use GibsonOS\Core\Attribute\GetModel;
use GibsonOS\Core\Controller\AbstractController;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Core\Exception\Model\DeleteError;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Manager\ModelManager;
use GibsonOS\Core\Manager\ServiceManager;
use GibsonOS\Core\Model\User;
use GibsonOS\Core\Model\User\Permission;
use GibsonOS\Core\Service\Response\AjaxResponse;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Store\AccountStore;
use GibsonOS\Module\Archivist\Strategy\StrategyInterface;
use JsonException;
use ReflectionException;

class AccountController extends AbstractController
{
    /**
     * @throws JsonException
     * @throws ReflectionException
     * @throws SelectError
     */
    #[CheckPermission(Permission::READ)]
    public function index(AccountStore $accountStore): AjaxResponse
    {
        $accountStore->setUser($this->sessionService->getUser() ?? new User());

        return $this->returnSuccess($accountStore->getList(), $accountStore->getCount());
    }

    public function edit(
        ServiceManager $serviceManager,
        string $strategy,
        array $configuration,
        array $parameters,
        int $configurationStep = 0,
        #[GetModel] Account $account = null
    ): AjaxResponse {
        if ($account !== null) {
            $configuration = array_merge($account->getConfiguration(), $configuration);
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
                if ($account !== null) {
                    foreach ($configurationParameters as $parameterName => $configurationParameter) {
                        $configurationParameter->setValue($configuration[$parameterName]);
                    }
                }

                return $this->returnSuccess($strategyDto->setParameters($configurationParameters));
            }
        }

        return $this->returnSuccess([
            'parameters' => [
                'name' => (new StringParameter('Name'))->setValue($account?->getName()),
            ],
            'configuration' => $strategyDto->getConfiguration(),
            'className' => $strategy,
            'lastStep' => true,
            'id' => $account?->getId(),
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
        #[GetMappedModel] Account $account
    ): AjaxResponse {
        $modelManager->save($account);

        return $this->returnSuccess();
    }

    /**
     * @throws DeleteError
     * @throws JsonException
     */
    #[CheckPermission(Permission::WRITE)]
    public function delete(
        ModelManager $modelManager,
        #[GetMappedModels(Account::class)] array $accounts
    ): AjaxResponse {
        foreach ($accounts as $account) {
            $modelManager->delete($account);
        }

        return $this->returnSuccess();
    }
}
