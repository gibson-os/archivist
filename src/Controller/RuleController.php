<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Controller;

use GibsonOS\Core\Controller\AbstractController;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Exception\FactoryError;
use GibsonOS\Core\Exception\GetError;
use GibsonOS\Core\Exception\LoginRequired;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\PermissionDenied;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Service\DirService;
use GibsonOS\Core\Service\PermissionService;
use GibsonOS\Core\Service\Response\AjaxResponse;
use GibsonOS\Core\Service\ServiceManagerService;
use GibsonOS\Core\Utility\JsonUtility;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Exception\StrategyException;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Repository\RuleRepository;
use GibsonOS\Module\Archivist\Store\RuleStore;
use GibsonOS\Module\Archivist\Strategy\StrategyInterface;
use GibsonOS\Module\Explorer\Dto\Parameter\DirectoryParameter;
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
    public function edit(
        ServiceManagerService $serviceManagerService,
        string $strategy,
        array $configuration,
        array $parameters,
        int $id = null
    ): AjaxResponse {
        $this->checkPermission(PermissionService::WRITE);

        /** @var StrategyInterface $strategyService */
        $strategyService = $serviceManagerService->get($strategy, StrategyInterface::class);
        $strategyDto = (new Strategy($strategyService->getName(), $strategy))->setConfig($configuration);

        if (!$strategyService->saveConfigurationParameters($strategyDto, $parameters)) {
            return $this->returnSuccess(
                $strategyDto->setParameters($strategyService->getConfigurationParameters($strategyDto))
            );
        }

        return $this->returnSuccess([
            'parameters' => [
                'name' => new StringParameter('Name'),
                'observedFilename' => new StringParameter('Beobachtungsregel'),
                'moveDirectory' => new DirectoryParameter('Ablage Verzeichnis'),
                'moveFilename' => new StringParameter('Ablage Dateiname'),
            ],
            'files' => $strategyService->getFiles($strategyDto),
            'config' => $strategyDto->getConfig(),
            'id' => $strategy,
        ]);
    }

    /**
     * @throws DateTimeError
     * @throws JsonException
     * @throws LoginRequired
     * @throws PermissionDenied
     * @throws SaveError
     */
    public function save(
        string $strategy,
        array $configuration,
        string $name,
        string $observedFilename,
        string $moveDirectory,
        string $moveFilename,
        int $id = null
    ): AjaxResponse {
        $this->checkPermission(PermissionService::WRITE);

        $rule = (new Rule())
            ->setId($id)
            ->setName($name)
            ->setStrategy($strategy)
            ->setConfiguration(JsonUtility::encode($configuration))
            ->setObservedFilename($observedFilename)
            ->setMoveDirectory($moveDirectory)
            ->setMoveFilename($moveFilename)
            ->setUserId($this->sessionService->getUserId() ?? 0)
        ;
        $rule->save();

        return $this->returnSuccess($rule);
    }

    /**
     * @throws DateTimeError
     * @throws FactoryError
     * @throws JsonException
     * @throws LoginRequired
     * @throws PermissionDenied
     * @throws SelectError
     */
    public function execute(
        ServiceManagerService $serviceManagerService,
        RuleRepository $ruleRepository,
        DirService $dirService,
        int $id
    ): AjaxResponse {
        $this->checkPermission(PermissionService::WRITE);

        $rule = $ruleRepository->getById($id);
        /** @var StrategyInterface $strategyService */
        $strategyService = $serviceManagerService->get($rule->getStrategy(), StrategyInterface::class);
        $strategy = (new Strategy($strategyService->getName(), $rule->getStrategy()))
            ->setConfig(JsonUtility::decode($rule->getConfiguration()))
        ;
        $files = $strategyService->getFiles($strategy);

        foreach ($files as $file) {
            $fileName = $dirService->addEndSlash($rule->getMoveDirectory()) . $rule->getMoveFilename();

            if (!file_exists($fileName)) {
                continue;
            }

            $strategyService->setFileResource($file);
            $resource = $file->getResource();

            if ($resource === null) {
                continue;
            }

            $newFile = fopen($fileName, 'w');
            stream_copy_to_stream($resource, $newFile);
            fclose($newFile);
        }

        return $this->returnSuccess();
    }
}
