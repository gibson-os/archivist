<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Controller;

use GibsonOS\Core\Controller\AbstractController;
use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Exception\FactoryError;
use GibsonOS\Core\Exception\GetError;
use GibsonOS\Core\Exception\LoginRequired;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\PermissionDenied;
use GibsonOS\Core\Service\PermissionService;
use GibsonOS\Core\Service\Response\AjaxResponse;
use GibsonOS\Core\Service\ServiceManagerService;
use GibsonOS\Core\Utility\JsonUtility;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Exception\StrategyException;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Store\RuleStore;
use GibsonOS\Module\Archivist\Strategy\StrategyInterface;
use JsonException;

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
     * @throws DateTimeError
     * @throws LoginRequired
     * @throws PermissionDenied
     * @throws SaveError
     * @throws StrategyException
     * @throws FactoryError
     * @throws JsonException
     */
    public function save(
        ServiceManagerService $serviceManagerService,
        string $strategy,
        array $configuration,
        array $parameters,
        string $name = null,
        string $observedFilename = null,
        string $moveDirectory = null,
        string $moveFilename = null,
        bool $active = false,
        int $id = null
    ): AjaxResponse {
        $this->checkPermission(PermissionService::WRITE);

        $strategyService = $serviceManagerService->get($strategy);

        if (!$strategyService instanceof StrategyInterface) {
            throw new StrategyException(sprintf(
                '%d is no instanceof of %d',
                get_class($strategyService),
                StrategyInterface::class
            ));
        }

        $strategyDto = (new Strategy($strategyService->getName(), $strategy))->setConfig($configuration);

        if (!$strategyService->saveConfigurationParameters($strategyDto, $parameters)) {
            return $this->returnSuccess(
                $strategyDto->setParameters($strategyService->getConfigurationParameters($strategyDto))
            );
        }

        return $this->returnSuccess($strategyService->getFiles($strategyDto, $parameters));
        $rule = (new Rule())
            ->setId($id)
            ->setName($name ?? '')
            ->setStrategy($strategy)
            ->setConfiguration(JsonUtility::encode($configuration))
            ->setObservedFilename($observedFilename ?: null)
            ->setMoveDirectory($moveDirectory ?? '')
            ->setMoveFilename($moveFilename ?? '')
            ->setActive($active)
            ->setUserId($this->sessionService->getUserId() ?? 0)
        ;
        $rule->save();

        return $this->returnSuccess($rule);
    }
}
