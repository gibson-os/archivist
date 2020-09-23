<?php declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Controller;

use GibsonOS\Core\Controller\AbstractController;
use GibsonOS\Core\Service\Response\AjaxResponse;

class RuleController extends AbstractController
{
    public function index(): AjaxResponse
    {
        return $this->returnSuccess();
    }
}
