<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Controller;

use GibsonOS\Core\Attribute\CheckPermission;
use GibsonOS\Core\Controller\AbstractController;
use GibsonOS\Core\Model\User\Permission;
use GibsonOS\Core\Service\Response\AjaxResponse;

class IndexController extends AbstractController
{
    #[CheckPermission(Permission::READ)]
    public function index(): AjaxResponse
    {
        return $this->returnSuccess();
    }
}
