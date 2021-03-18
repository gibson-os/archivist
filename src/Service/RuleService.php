<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Service;

use DateTimeImmutable;
use Exception;
use GibsonOS\Core\Exception\CreateError;
use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Exception\DeleteError;
use GibsonOS\Core\Exception\FileNotFound;
use GibsonOS\Core\Exception\GetError;
use GibsonOS\Core\Exception\Model\DeleteError as ModelDeleteError;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Exception\SetError;
use GibsonOS\Core\Service\AbstractService;
use GibsonOS\Core\Service\DirService;
use GibsonOS\Core\Service\FileService;
use GibsonOS\Module\Archivist\Model\Index;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Repository\IndexRepository;

class RuleService extends AbstractService
{
    private FileService $fileService;

    private DirService $dirService;

    private IndexRepository $indexRepository;

    public function __construct(FileService $fileService, DirService $dirService, IndexRepository $indexRepository)
    {
        $this->fileService = $fileService;
        $this->dirService = $dirService;
        $this->indexRepository = $indexRepository;
    }

    /**
     * @throws CreateError
     * @throws DateTimeError
     * @throws DeleteError
     * @throws FileNotFound
     * @throws GetError
     * @throws SaveError
     * @throws SetError
     */
    public function moveFile(Index $indexedFile): void
    {
        $outputPath = $indexedFile->getOutputPath();

        if ($outputPath === null) {
            return;
        }

        $rule = $indexedFile->getRule();

        if ($rule instanceof Rule) {
            $rule->save();
        }

        $this->fileService->move($indexedFile->getInputPath(), $outputPath);
    }

    /**
     * @throws DateTimeError
     * @throws GetError
     * @throws SaveError
     * @throws Exception
     */
    public function indexFiles(Rule $rule): void
    {
//        foreach ($this->dirService->getFiles($directory, $rule->getObservedFilename() ?? '*') as $filename) {
//            try {
//                $indexedFile = $this->indexRepository->getByInputPath($filename);
//            } catch (SelectError $e) {
//                $indexedFile = (new Index())
//                    ->setRule($rule->isActive() ? $rule : null)
//                    ->setInputPath($filename)
//                ;
//                $indexedFile->setOutputPath($this->getOutputPath($indexedFile));
//            }
//
//            $size = filesize($filename);
//
//            if ($size !== 0) {
//                if ($size === $indexedFile->getSize()) {
//                    if ($indexedFile->getChanged()->format('U') < time() - 30) {
//                        try {
//                            $this->moveFile($indexedFile);
//                            $indexedFile->delete();
//                        } catch (CreateError | DeleteError | FileNotFound | GetError | SetError | DateTimeError | ModelDeleteError $e) {
//                            // Error
//                        }
//                    }
//
//                    continue;
//                }
//
//                $indexedFile->setChanged(new DateTimeImmutable());
//            }
//
//            $indexedFile
//                ->setSize($size)
//                ->save()
//            ;
//        }
    }
}
