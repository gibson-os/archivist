<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Controller;

use GibsonOS\Core\Attribute\CheckPermission;
use GibsonOS\Core\Attribute\GetMappedModel;
use GibsonOS\Core\Attribute\GetMappedModels;
use GibsonOS\Core\Attribute\GetModel;
use GibsonOS\Core\Controller\AbstractController;
use GibsonOS\Core\Exception\FactoryError;
use GibsonOS\Core\Exception\Model\DeleteError;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Manager\ModelManager;
use GibsonOS\Core\Manager\ServiceManager;
use GibsonOS\Core\Model\User;
use GibsonOS\Core\Model\User\Permission;
use GibsonOS\Core\Service\CommandService;
use GibsonOS\Core\Service\Response\AjaxResponse;
use GibsonOS\Module\Archivist\Command\IndexerCommand;
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

    /**
     * @throws JsonException
     * @throws SaveError
     * @throws FactoryError
     */
    #[CheckPermission(Permission::WRITE)]
    public function execute(
        ServiceManager $serviceManager,
        CommandService $commandService,
        ModelManager $modelManager,
        #[GetModel(['id' => 'id', 'user_id' => 'session.user.id'])] Account $account,
        array $parameters = [],
    ): AjaxResponse {
        $account->setExecutionParameters($parameters);
        $strategy = $serviceManager->get($account->getStrategy(), StrategyInterface::class);
        $strategy->setExecuteParameters($account, $parameters);
        $executeParameters = $strategy->getExecuteParameters($account);

        if (count($executeParameters)) {
            $modelManager->saveWithoutChildren($account);

            return $this->returnSuccess($executeParameters);
        }

        $modelManager->saveWithoutChildren($account->setMessage('Starte'));
        $commandService->executeAsync(IndexerCommand::class, ['accountId' => $account->getId()]);

        return $this->returnSuccess([]);
    }

    public function status(#[GetModel(['id' => 'id', 'user_id' => 'session.user.id'])] Account $account): AjaxResponse
    {
        return $this->returnSuccess($account);
    }

    /**
     * @throws JsonException
     * @throws SaveError
     * @throws FactoryError
     */
    #[CheckPermission(Permission::WRITE)]
    public function save(
        ServiceManager $serviceManager,
        ModelManager $modelManager,
        #[GetMappedModel(['id' => 'id', 'user_id' => 'session.user.id'], ['user' => 'session.user'])] Account $account,
        array $configuration = [],
    ): AjaxResponse {
        $strategy = $serviceManager->get($account->getStrategy(), StrategyInterface::class);
        $strategy->setAccountParameters($account, $configuration);

        $modelManager->saveWithoutChildren($account);

        return $this->returnSuccess();
    }

    /**
     * @throws DeleteError
     * @throws JsonException
     */
    #[CheckPermission(Permission::WRITE)]
    public function delete(
        ModelManager $modelManager,
        //@todo #[GetMappedModels(Account::class, ['id' => 'id', 'user_id' => 'session.user.id'])] array $accounts klappt mit session wert nicht
        #[GetMappedModels(Account::class)] array $accounts
    ): AjaxResponse {
        foreach ($accounts as $account) {
            $modelManager->delete($account);
        }

        return $this->returnSuccess();
    }
}
