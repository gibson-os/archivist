<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Service;

use GibsonOS\Core\Exception\FactoryError;
use GibsonOS\Core\Service\AbstractService;
use GibsonOS\Core\Service\DirService;
use GibsonOS\Core\Service\FileService;
use GibsonOS\Core\Service\ServiceManagerService;
use GibsonOS\Core\Utility\JsonUtility;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Repository\IndexRepository;
use GibsonOS\Module\Archivist\Strategy\StrategyInterface;
use JsonException;

class RuleService extends AbstractService
{
    private FileService $fileService;

    private DirService $dirService;

    private IndexRepository $indexRepository;

    private ServiceManagerService $serviceManagerService;

    public function __construct(
        FileService $fileService,
        DirService $dirService,
        IndexRepository $indexRepository,
        ServiceManagerService $serviceManagerService
    ) {
        $this->fileService = $fileService;
        $this->dirService = $dirService;
        $this->indexRepository = $indexRepository;
        $this->serviceManagerService = $serviceManagerService;
    }

    /**
     * @throws FactoryError
     * @throws JsonException
     */
    public function executeRule(Rule $rule): void
    {
        /** @var StrategyInterface $strategyService */
        $strategyService = $this->serviceManagerService->get($rule->getStrategy(), StrategyInterface::class);
        $strategy = (new Strategy($strategyService->getName(), $rule->getStrategy()))
            ->setConfig(JsonUtility::decode($rule->getConfiguration()))
        ;
        $files = $strategyService->getFiles($strategy);

        foreach ($files as $file) {
            $fileName = $this->dirService->addEndSlash($rule->getMoveDirectory()) . $rule->getMoveFilename();

            if (file_exists($fileName)) {
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
    }
}
