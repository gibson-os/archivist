<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Service;

use GibsonOS\Core\Exception\FactoryError;
use GibsonOS\Core\Service\AbstractService;
use GibsonOS\Core\Service\DirService;
use GibsonOS\Core\Service\FileService;
use GibsonOS\Core\Service\ServiceManagerService;
use GibsonOS\Core\Service\TwigService;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Exception\RuleException;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Repository\IndexRepository;
use GibsonOS\Module\Archivist\Strategy\StrategyInterface;
use JsonException;
use Twig\Extension\StringLoaderExtension;

class RuleService extends AbstractService
{
    private FileService $fileService;

    private DirService $dirService;

    private IndexRepository $indexRepository;

    private ServiceManagerService $serviceManagerService;

    private TwigService $twigService;

    public function __construct(
        FileService $fileService,
        DirService $dirService,
        IndexRepository $indexRepository,
        ServiceManagerService $serviceManagerService,
        TwigService $twigService
    ) {
        $this->fileService = $fileService;
        $this->dirService = $dirService;
        $this->indexRepository = $indexRepository;
        $this->serviceManagerService = $serviceManagerService;
        $this->twigService = $twigService;
        $this->twigService->getTwig()->addExtension(new StringLoaderExtension());
    }

    /**
     * @throws FactoryError
     * @throws JsonException
     */
    public function executeRule(Rule $rule, array $configuration): void
    {
        /** @var StrategyInterface $strategyService */
        $strategyService = $this->serviceManagerService->get($rule->getStrategy(), StrategyInterface::class);
        $strategy = (new Strategy($strategyService->getName(), $rule->getStrategy()))->setConfig($configuration);
        $files = $strategyService->getFiles($strategy);

        foreach ($files as $file) {
            $matches = [];
            preg_match('/' . ($rule->getObservedFilename() ?? '.*') . '/', $file->getName(), $matches);

            if (empty($matches)) {
                continue;
            }

            $context = [
                'template' => $this->dirService->addEndSlash($rule->getMoveDirectory()) . $rule->getMoveFilename(),
                'createDate' => $file->getCreateDate(),
            ];

            foreach ($matches as $index => $match) {
                $context['match' . $index] = $match;
            }

            $newFileName = $this->twigService->getTwig()->render('@archivist/fileName.html.twig', $context);

            if (file_exists($newFileName)) {
                throw new RuleException(sprintf('File %s exists', $newFileName));
            }

            $strategyService->setFileResource($file);
            $resource = $file->getResource();

            if ($resource === null) {
                throw new RuleException(sprintf('Resource for %s not set', $file->getName()));
            }

            $newFile = fopen($newFileName, 'w');
            stream_copy_to_stream($resource, $newFile);
            fclose($newFile);
        }

        $strategyService->unload();
    }
}
