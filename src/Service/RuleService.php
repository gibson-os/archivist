<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Service;

use GibsonOS\Core\Exception\FactoryError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Service\AbstractService;
use GibsonOS\Core\Service\DirService;
use GibsonOS\Core\Service\FileService;
use GibsonOS\Core\Service\ServiceManagerService;
use GibsonOS\Core\Service\TwigService;
use GibsonOS\Core\Utility\JsonUtility;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Exception\RuleException;
use GibsonOS\Module\Archivist\Model\Index;
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
    public function executeRule(Rule $rule): void
    {
        /** @var StrategyInterface $strategyService */
        $strategyService = $this->serviceManagerService->get($rule->getStrategy(), StrategyInterface::class);
        $strategy = (new Strategy($strategyService->getName(), $rule->getStrategy()))
            ->setConfig(JsonUtility::decode($rule->getConfiguration()))
        ;
        $rule->setMessage('Starte Indexierung')->save();
        $files = $strategyService->getFiles($strategy);

        foreach ($files as $file) {
            $rule->setMessage(sprintf('Indexiere %s', $file->getName()))->save();
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
            $inputPath = $this->dirService->addEndSlash($file->getPath()) . $file->getName();
            $index = (new Index())
                ->setRule($rule)
                ->setInputPath($inputPath)
                ->setOutputPath($newFileName)
                ->setSize(filesize($newFileName))
            ;

            if (file_exists($newFileName)) {
                try {
                    $this->indexRepository->getByInputPath($rule->getId() ?? 0, $inputPath);

                    continue;
                } catch (SelectError $e) {
                    $index->setError('Datei existiert bereits!')->save();

                    throw new RuleException(sprintf('File %s exists', $newFileName));
                }
            }

            $rule->setMessage(sprintf('Lade Datei %s', $file->getName()))->save();
            $strategyService->setFileResource($file);
            $resource = $file->getResource();

            if ($resource === null) {
                $index->setError('Datei konnte nicht geladen werden!')->save();

                throw new RuleException(sprintf('Resource for %s not set', $file->getName()));
            }

            $newFile = fopen($newFileName, 'w');
            stream_copy_to_stream($resource, $newFile);
            fclose($newFile);
            $index->save();
        }

        $rule->setMessage('Fertig')->save();
        $strategyService->unload($strategy);
    }
}
