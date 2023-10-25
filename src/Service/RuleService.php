<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Service;

use GibsonOS\Core\Exception\CreateError;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Manager\ModelManager;
use GibsonOS\Core\Service\DirService;
use GibsonOS\Core\Service\FileService;
use GibsonOS\Core\Service\TwigService;
use GibsonOS\Core\Wrapper\ModelWrapper;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Exception\RuleException;
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

class RuleService
{
    public const RULE_LOCK_PREFIX = 'archivistIndexer';

    public function __construct(
        private readonly FileService $fileService,
        private readonly DirService $dirService,
        private readonly IndexRepository $indexRepository,
        private readonly TwigService $twigService,
        private readonly LoggerInterface $logger,
        private readonly ModelManager $modelManager,
        private readonly ModelWrapper $modelWrapper,
    ) {
        $this->twigService->getTwig()->addExtension(new StringLoaderExtension());
    }

    /**
     * @throws RuleException
     * @throws CreateError
     * @throws SaveError
     * @throws JsonException
     * @throws ReflectionException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function execute(Rule $rule, File $file, StrategyInterface $strategy): void
    {
        $account = $rule->getAccount();
        $matches = [];
        preg_match('/' . ($rule->getObservedFilename() ?? '.*') . '/', $file->getName(), $matches);

        if (empty($matches)) {
            $this->logger->info(sprintf('No match for rule "%s"', $rule->getName()));

            return;
        }

        $observedContent = $rule->getObservedContent();
        $contentMatches = [];

        if ($observedContent !== null) {
            preg_match(
                '/' . $observedContent . '/s',
                $file->getContent() ?? '',
                $contentMatches
            );

            if (empty($contentMatches)) {
                $this->logger->info(sprintf('No content match for rule "%s"', $rule->getName()));

                return;
            }
        }

        $this->logger->info(sprintf('Indexing file "%s"', $file->getName()));
        $this->modelManager->saveWithoutChildren($account->setMessage(sprintf('Indexiere "%s"', $file->getName())));

        $context = [
            'template' => $this->dirService->addEndSlash($rule->getMoveDirectory()) . $rule->getMoveFilename(),
            'createDate' => $file->getCreateDate(),
        ];

        foreach ($matches as $index => $match) {
            $context['match' . $index] = $match;
        }

        foreach ($contentMatches as $index => $contentMatch) {
            $context['contentMatch' . $index] = $contentMatch;
        }

        $newFileName = $this->twigService->getTwig()->render('@archivist/fileName.html.twig', $context);
        $newFileName = html_entity_decode(html_entity_decode($newFileName));
        $newFileName = str_replace(['\\', ':', '*', '?', '"', '<', '>', '|'], ' ', $newFileName);
        $newFileName = preg_replace('/\s{2,}/', ' ', $newFileName);
        $newFileName = trim(preg_replace('/ (\.|\)|\?|!)/', '$1', $newFileName));
        $inputPath = $this->dirService->addEndSlash($file->getPath()) . $file->getName();
        $index = (new Index($this->modelWrapper))
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

            return;
        }

        $this->logger->info(sprintf('Load file %s', $file->getName()));
        $this->modelManager->saveWithoutChildren($account->setMessage(sprintf('Lade Datei %s', $file->getName())));
        $strategy->setFileResource($file, $account);
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
