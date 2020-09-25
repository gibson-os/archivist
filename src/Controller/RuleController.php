<?php declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Controller;

use GibsonOS\Core\Controller\AbstractController;
use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Exception\GetError;
use GibsonOS\Core\Exception\LoginRequired;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\PermissionDenied;
use GibsonOS\Core\Service\PermissionService;
use GibsonOS\Core\Service\Response\AjaxResponse;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Store\RuleStore;

class RuleController extends AbstractController
{
    /**
     * @throws GetError
     * @throws LoginRequired
     * @throws PermissionDenied
     */
    public function index(RuleStore $ruleStore, int $start = 0, int $limit = 100, array $sort = []): AjaxResponse
    {
        $this->checkPermission(PermissionService::READ);

        $ruleStore->setLimit($limit, $start);
        $ruleStore->setSortByExt($sort);

        return $this->returnSuccess($ruleStore->getList(), $ruleStore->getCount());
    }

    /**
     * @throws LoginRequired
     * @throws PermissionDenied
     * @throws DateTimeError
     * @throws SaveError
     */
    public function save(
        string $name,
        string $observeDirectory,
        string $observeFilename,
        string $moveDirectory,
        string $moveFilename,
        int $count,
        bool $active,
        int $id = null
    ): AjaxResponse {
        $this->checkPermission(PermissionService::WRITE);

        $rule = (new Rule())
            ->setId($id)
            ->setName($name)
            ->setObserveDirectory($observeDirectory)
            ->setObserveFilename($observeFilename ?: null)
            ->setMoveDirectory($moveDirectory)
            ->setMoveFilename($moveFilename)
            ->setCount($count)
            ->setActive($active)
            ->setUserId($this->sessionService->getUserId() ?? 0)
        ;
        $rule->save();

        return $this->returnSuccess($rule);
    }
}
