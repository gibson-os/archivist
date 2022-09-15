<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Controller;

use GibsonOS\Core\Attribute\CheckPermission;
use GibsonOS\Core\Attribute\GetModel;
use GibsonOS\Core\Controller\AbstractController;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Model\User\Permission;
use GibsonOS\Core\Service\Response\AjaxResponse;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Store\IndexStore;
use JsonException;
use ReflectionException;

class IndexController extends AbstractController
{
    /**
     * @throws SelectError
     * @throws JsonException
     * @throws ReflectionException
     */
    #[CheckPermission(Permission::READ)]
    public function index(
        IndexStore $indexStore,
        #[GetModel(['id' => 'accountId'])] ?Account $account,
        #[GetModel(['id' => 'ruleId'])] ?Rule $rule,
    ): AjaxResponse {
        $indexStore
            ->setAccount($account)
            ->setRule($rule)
        ;

        return $this->returnSuccess($indexStore->getList(), $indexStore->getCount());
    }
}
