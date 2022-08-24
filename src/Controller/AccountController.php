<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Controller;

use GibsonOS\Core\Attribute\CheckPermission;
use GibsonOS\Core\Attribute\GetMappedModel;
use GibsonOS\Core\Attribute\GetMappedModels;
use GibsonOS\Core\Attribute\GetModel;
use GibsonOS\Core\Controller\AbstractController;
use GibsonOS\Core\Exception\Model\DeleteError;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Manager\ModelManager;
use GibsonOS\Core\Model\User;
use GibsonOS\Core\Model\User\Permission;
use GibsonOS\Core\Service\CommandService;
use GibsonOS\Core\Service\Response\AjaxResponse;
use GibsonOS\Module\Archivist\Command\IndexerCommand;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Store\AccountStore;
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
     * @throws ReflectionException
     */
    #[CheckPermission(Permission::WRITE)]
    public function execute(
        CommandService $commandService,
        ModelManager $modelManager,
        #[GetModel] Account $account
    ): AjaxResponse {
        $modelManager->save($account->setActive(true)->setMessage('Starte'));
        $commandService->executeAsync(IndexerCommand::class, ['accountId' => $account->getId()]);

        return $this->returnSuccess($account);
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
        $account->setUser($this->sessionService->getUser() ?? new User());
        $modelManager->save($account);

        return $this->returnSuccess($account);
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
