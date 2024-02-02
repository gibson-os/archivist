<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Dto\Form;

use GibsonOS\Core\Dto\Form\ModelFormConfig;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Model\Rule;

/**
 * @extends ModelFormConfig<Rule>
 */
class RuleFormConfig extends ModelFormConfig
{
    public function __construct(private readonly Account $account, ?Rule $model = null)
    {
        parent::__construct($model);
    }

    public function getAccount(): Account
    {
        return $this->account;
    }
}
