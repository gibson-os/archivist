<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Controller;

use GibsonOS\Core\Controller\AbstractController;
use GibsonOS\Core\Service\Response\AjaxResponse;

class IndexController extends AbstractController
{
    public function index(): AjaxResponse
    {
        return $this->returnSuccess();
    }
}
