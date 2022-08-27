<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Service;

use GibsonOS\Core\Exception\CreateError;
use GibsonOS\Core\Exception\FactoryError;
use GibsonOS\Core\Exception\Flock\LockError;
use GibsonOS\Core\Exception\Flock\UnlockError;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Manager\ModelManager;
use GibsonOS\Core\Manager\ServiceManager;
use GibsonOS\Core\Service\DirService;
use GibsonOS\Core\Service\FileService;
use GibsonOS\Core\Service\LockService;
use GibsonOS\Core\Service\TwigService;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Exception\RuleException;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Model\Index;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Repository\IndexRepository;
use GibsonOS\Module\Archivist\Strategy\StrategyInterface;
use JsonException;
use Psr\Log\LoggerInterface;
use ReflectionException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\StringLoaderExtension;

class AccountService
{
    public const RULE_LOCK_PREFIX = 'archivistIndexer';

    public function __construct(
        private FileService $fileService,
        private DirService $dirService,
        private IndexRepository $indexRepository,
        private ServiceManager $serviceManager,
        private TwigService $twigService,
        private LoggerInterface $logger,
        private LockService $lockService,
        private ModelManager $modelManager
    ) {
        $this->twigService->getTwig()->addExtension(new StringLoaderExtension());
    }

    /**
     * @throws FactoryError
     * @throws JsonException
     * @throws RuleException
     * @throws CreateError
     * @throws LockError
     * @throws UnlockError
     * @throws SaveError
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws ReflectionException
     */
    public function execute(Account $account): bool
    {
        $this->logger->info(sprintf('Start indexing for account %s', $account->getName()));
        $strategyService = $this->serviceManager->get($account->getStrategy(), StrategyInterface::class);

        $lockName = self::RULE_LOCK_PREFIX . $strategyService->getLockName($account);
        $this->lockService->lock($lockName);

        $rules = array_filter(
            $account->getRules(),
            fn (Rule $rule): bool => $rule->isActive()
        );

        if (count($rules) === 0) {
            $this->logger->warning(sprintf('No active rules for account %s!', $account->getName()));
            $this->modelManager->save($account->setMessage('Keine activen Regeln vorhanden'));

            return false;
        }

        $this->modelManager->save($account->setMessage('Ermittel Dateien'));
        $this->logger->info(sprintf(
            'Get files with %s strategy for account %s',
            $strategyService->getName(),
            $account->getName()
        ));

        foreach ($strategyService->getFiles($account) as $file) {
            if (!$file instanceof File) {
                continue;
            }

            foreach ($rules as $rule) {
                $matches = [];
                preg_match('/' . ($rule->getObservedFilename() ?? '.*') . '/', $file->getName(), $matches);

                if (empty($matches)) {
                    continue;
                }

                $this->logger->info(sprintf('Indexing file "%s"', $file->getName()));
                $this->modelManager->save($rule->setMessage(sprintf('Indexiere "%s"', $file->getName())));

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
                    } catch (SelectError) {
                        $this->logger->warning(sprintf('File %s already exists!', $newFileName));
                        $this->modelManager->save($index->setError('Datei existiert bereits!'));
                    }

                    continue;
                }

                $this->logger->info(sprintf('Load file %s', $file->getName()));
                $this->modelManager->save($rule->setMessage(sprintf('Lade Datei %s', $file->getName())));
                $strategyService->setFileResource($file, $rule);
                $resource = $file->getResource();

                if ($resource === null) {
                    $this->logger->warning(sprintf('Resource for %s not set!', $file->getName()));
                    $this->modelManager->save($index->setError('Datei konnte nicht geladen werden!'));

                    throw new RuleException(sprintf('Resource for %s not set', $file->getName()));
                }

                $dir = $this->fileService->getDir($newFileName);

                if (!file_exists($dir)) {
                    $this->dirService->create($dir);
                }

                $newFile = fopen($newFileName, 'w');
                stream_copy_to_stream($resource, $newFile);
                fclose($newFile);
                $this->modelManager->save($index->setSize(filesize($newFileName)));
            }
        }

        $this->modelManager->save($account->setMessage('Fertig'));
        $strategyService->unload($account);
        $this->lockService->unlock($lockName);

        return true;
    }
}
