<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Controller;

use GibsonOS\Core\Attribute\CheckPermission;
use GibsonOS\Core\Attribute\GetModel;
use GibsonOS\Core\Controller\AbstractController;
use GibsonOS\Core\Enum\Permission;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Service\Response\AjaxResponse;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Store\IndexStore;
use JsonException;
use MDO\Exception\ClientException;
use MDO\Exception\RecordException;
use ReflectionException;

class IndexController extends AbstractController
{
    /**
     * @throws JsonException
     * @throws ReflectionException
     * @throws SelectError
     * @throws ClientException
     * @throws RecordException
     */
    #[CheckPermission([Permission::READ])]
    public function get(
        IndexStore $indexStore,
        #[GetModel(['id' => 'accountId'])]
        ?Account $account,
        #[GetModel(['id' => 'ruleId'])]
        ?Rule $rule,
        array $sort = [],
    ): AjaxResponse {
        $indexStore->setSortByExt($sort);
        $indexStore
            ->setAccount($account)
            ->setRule($rule)
        ;

        return $this->returnSuccess($indexStore->getList(), $indexStore->getCount());
    }
}
