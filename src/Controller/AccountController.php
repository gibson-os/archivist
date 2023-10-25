<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Controller;

use GibsonOS\Core\Attribute\CheckPermission;
use GibsonOS\Core\Attribute\GetMappedModel;
use GibsonOS\Core\Attribute\GetMappedModels;
use GibsonOS\Core\Attribute\GetModel;
use GibsonOS\Core\Controller\AbstractController;
use GibsonOS\Core\Enum\Permission;
use GibsonOS\Core\Exception\FactoryError;
use GibsonOS\Core\Exception\Model\DeleteError;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Manager\ModelManager;
use GibsonOS\Core\Manager\ServiceManager;
use GibsonOS\Core\Model\User;
use GibsonOS\Core\Service\CommandService;
use GibsonOS\Core\Service\Response\AjaxResponse;
use GibsonOS\Core\Wrapper\ModelWrapper;
use GibsonOS\Module\Archivist\Command\IndexerCommand;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Store\AccountStore;
use GibsonOS\Module\Archivist\Strategy\StrategyInterface;
use JsonException;
use MDO\Exception\ClientException;
use MDO\Exception\RecordException;
use ReflectionException;

class AccountController extends AbstractController
{
    /**
     * @throws JsonException
     * @throws ReflectionException
     * @throws SelectError
     * @throws ClientException
     * @throws RecordException
     */
    #[CheckPermission([Permission::READ])]
    public function get(ModelWrapper $modelWrapper, AccountStore $accountStore): AjaxResponse
    {
        $accountStore->setUser($this->sessionService->getUser() ?? new User($modelWrapper));

        return $this->returnSuccess($accountStore->getList(), $accountStore->getCount());
    }

    /**
     * @throws JsonException
     * @throws SaveError
     * @throws FactoryError
     */
    #[CheckPermission([Permission::WRITE])]
    public function postExecute(
        ServiceManager $serviceManager,
        CommandService $commandService,
        ModelManager $modelManager,
        #[GetModel(['id' => 'id', 'user_id' => 'session.user.id'])]
        Account $account,
        array $parameters = [],
    ): AjaxResponse {
        if (count(array_filter($account->getRules(), fn (Rule $rule): bool => $rule->isActive())) === 0) {
            return $this->returnFailure('Account has no active rules!');
        }

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

    #[CheckPermission([Permission::WRITE])]
    public function getStatus(#[GetModel(['id' => 'id', 'user_id' => 'session.user.id'])] Account $account): AjaxResponse
    {
        return $this->returnSuccess($account);
    }

    /**
     * @throws JsonException
     * @throws SaveError
     * @throws FactoryError
     */
    #[CheckPermission([Permission::WRITE])]
    public function post(
        ServiceManager $serviceManager,
        ModelManager $modelManager,
        #[GetMappedModel(['id' => 'id', 'user_id' => 'session.user.id'], ['user' => 'session.user'])]
        Account $account,
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
    #[CheckPermission([Permission::DELETE])]
    public function delete(
        ModelManager $modelManager,
        // @todo #[GetMappedModels(Account::class, ['id' => 'id', 'user_id' => 'session.user.id'])] array $accounts klappt mit session wert nicht
        #[GetMappedModels(Account::class)]
        array $accounts
    ): AjaxResponse {
        foreach ($accounts as $account) {
            $modelManager->delete($account);
        }

        return $this->returnSuccess();
    }
}
