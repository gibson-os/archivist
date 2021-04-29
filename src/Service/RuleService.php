<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Service;

use GibsonOS\Core\Exception\CreateError;
use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Exception\FactoryError;
use GibsonOS\Core\Exception\Flock\LockError;
use GibsonOS\Core\Exception\Flock\UnlockError;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Service\AbstractService;
use GibsonOS\Core\Service\DirService;
use GibsonOS\Core\Service\FileService;
use GibsonOS\Core\Service\LockService;
use GibsonOS\Core\Service\ServiceManagerService;
use GibsonOS\Core\Service\TwigService;
use GibsonOS\Core\Utility\JsonUtility;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Exception\RuleException;
use GibsonOS\Module\Archivist\Model\Index;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Repository\IndexRepository;
use GibsonOS\Module\Archivist\Strategy\StrategyInterface;
use JsonException;
use Psr\Log\LoggerInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\StringLoaderExtension;

class RuleService extends AbstractService
{
    public const RULE_LOCK_PREFIX = 'archivistIndexer';

    private FileService $fileService;

    private DirService $dirService;

    private IndexRepository $indexRepository;

    private ServiceManagerService $serviceManagerService;

    private TwigService $twigService;

    private LoggerInterface $logger;

    private LockService $lockService;

    public function __construct(
        FileService $fileService,
        DirService $dirService,
        IndexRepository $indexRepository,
        ServiceManagerService $serviceManagerService,
        TwigService $twigService,
        LoggerInterface $logger,
        LockService $lockService
    ) {
        $this->fileService = $fileService;
        $this->dirService = $dirService;
        $this->indexRepository = $indexRepository;
        $this->serviceManagerService = $serviceManagerService;
        $this->twigService = $twigService;
        $this->twigService->getTwig()->addExtension(new StringLoaderExtension());
        $this->logger = $logger;
        $this->lockService = $lockService;
    }

    /**
     * @throws FactoryError
     * @throws JsonException
     * @throws RuleException
     * @throws CreateError
     * @throws DateTimeError
     * @throws LockError
     * @throws UnlockError
     * @throws SaveError
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function executeRule(Rule $rule): void
    {
        $this->logger->info(sprintf('Start indexing for rule %s', $rule->getName()));
        /** @var StrategyInterface $strategyService */
        $strategyService = $this->serviceManagerService->get($rule->getStrategy(), StrategyInterface::class);

        $lockName = self::RULE_LOCK_PREFIX . $strategyService->getLockName($rule);
        $this->lockService->lock($lockName);

        $strategy = (new Strategy($strategyService->getName(), $rule->getStrategy()))
            ->setConfig(JsonUtility::decode($rule->getConfiguration()))
        ;
        $rule->setMessage('Ermittel Dateien')->save();
        $this->logger->info(sprintf('Get files with %s strategy', $strategyService->getName()));

        foreach ($strategyService->getFiles($strategy, $rule) as $file) {
            if (!$file instanceof File) {
                continue;
            }

            $matches = [];
            preg_match('/' . ($rule->getObservedFilename() ?? '.*') . '/', $file->getName(), $matches);

            if (empty($matches)) {
                continue;
            }

            $this->logger->info(sprintf('Indexing file "%s"', $file->getName()));
            $rule->setMessage(sprintf('Indexiere "%s"', $file->getName()))->save();

            $context = [
                'template' => $this->dirService->addEndSlash($rule->getMoveDirectory()) . $rule->getMoveFilename(),
                'createDate' => $file->getCreateDate(),
            ];

            foreach ($matches as $index => $match) {
                $context['match' . $index] = $match;
            }

            $newFileName = $this->twigService->getTwig()->render('@archivist/fileName.html.twig', $context);
            $newFileName = str_replace(['\\', ':', '*', '?', '"', '<', '>', '|'], '', $newFileName);
            $inputPath = $this->dirService->addEndSlash($file->getPath()) . $file->getName();
            $index = (new Index())
                ->setRule($rule)
                ->setInputPath($inputPath)
                ->setOutputPath($newFileName)
            ;

            if (file_exists($newFileName)) {
                try {
                    $this->indexRepository->getByInputPath($rule->getId() ?? 0, $inputPath);
                } catch (SelectError $e) {
                    $this->logger->warning(sprintf('File %s already exists!', $newFileName));
                    $index->setError('Datei existiert bereits!')->save();
                }

                continue;
            }

            $this->logger->info(sprintf('Load file %s', $file->getName()));
            $rule->setMessage(sprintf('Lade Datei %s', $file->getName()))->save();
            $strategyService->setFileResource($file, $rule);
            $resource = $file->getResource();

            if ($resource === null) {
                $this->logger->warning(sprintf('Resource for %s not set!', $file->getName()));
                $index->setError('Datei konnte nicht geladen werden!')->save();

                throw new RuleException(sprintf('Resource for %s not set', $file->getName()));
            }

            $dir = $this->fileService->getDir($newFileName);

            if (!file_exists($dir)) {
                $this->dirService->create($dir);
            }

            $newFile = fopen($newFileName, 'w');
            stream_copy_to_stream($resource, $newFile);
            fclose($newFile);
            $index
                ->setSize(filesize($newFileName))
                ->save();
        }

        $rule
            ->setActive(false)
            ->setMessage('Fertig')
            ->save()
        ;
        $strategyService->unload($strategy);
        $this->lockService->unlock($lockName);
    }
}
