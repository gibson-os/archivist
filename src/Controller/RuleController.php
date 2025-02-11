<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Controller;

use GibsonOS\Core\Attribute\CheckPermission;
use GibsonOS\Core\Attribute\GetMappedModel;
use GibsonOS\Core\Attribute\GetModel;
use GibsonOS\Core\Attribute\GetModels;
use GibsonOS\Core\Controller\AbstractController;
use GibsonOS\Core\Enum\Permission;
use GibsonOS\Core\Exception\FormException;
use GibsonOS\Core\Exception\Model\DeleteError;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Manager\ModelManager;
use GibsonOS\Core\Service\Response\AjaxResponse;
use GibsonOS\Module\Archivist\Dto\Form\RuleFormConfig;
use GibsonOS\Module\Archivist\Form\RuleForm;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Store\RuleStore;
use JsonException;
use MDO\Exception\ClientException;
use MDO\Exception\RecordException;
use ReflectionException;

class RuleController extends AbstractController
{
    /**
     * @throws JsonException
     * @throws RecordException
     * @throws ReflectionException
     * @throws SelectError
     * @throws ClientException
     */
    #[CheckPermission([Permission::READ])]
    public function get(
        RuleStore $ruleStore,
        #[GetModel(['id' => 'accountId', 'user_id' => 'session.userId'])]
        Account $account,
        int $start = 0,
        int $limit = 100,
        array $sort = [],
    ): AjaxResponse {
        $ruleStore->setAccount($account);
        $ruleStore->setLimit($limit, $start);
        $ruleStore->setSortByExt($sort);

        return $this->returnSuccess($ruleStore->getList(), $ruleStore->getCount());
    }

    /**
     * @throws FormException
     */
    #[CheckPermission([Permission::WRITE])]
    public function getEdit(
        #[GetModel(['id' => 'accountId', 'user_id' => 'session.userId'])]
        Account $account,
        #[GetModel(['id' => 'id', 'account_id' => 'accountId'])]
        ?Rule $rule = null,
    ): AjaxResponse {
        $form = new RuleForm();

        return $this->returnSuccess($form->getForm(new RuleFormConfig($account, $rule)));
    }

    /**
     * @throws JsonException
     * @throws ReflectionException
     * @throws SaveError
     * @throws RecordException
     */
    #[CheckPermission([Permission::WRITE])]
    public function post(
        ModelManager $modelManager,
        #[GetMappedModel]
        Rule $rule,
    ): AjaxResponse {
        $modelManager->saveWithoutChildren($rule);

        return $this->returnSuccess($rule);
    }

    /**
     * @param Rule[] $rules
     *
     * @throws JsonException
     * @throws DeleteError
     */
    #[CheckPermission([Permission::DELETE])]
    public function delete(
        ModelManager $modelManager,
        #[GetModels(Rule::class)]
        array $rules,
    ): AjaxResponse {
        foreach ($rules as $rule) {
            $modelManager->delete($rule);
        }

        return $this->returnSuccess();
    }
}
