<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Service;

use GibsonOS\Core\Service\AbstractService;
use GibsonOS\Core\Service\FileService;
use GibsonOS\Module\Archivist\Model\Index;
use GibsonOS\Module\Archivist\Model\Rule;

class RuleService extends AbstractService
{
    /**
     * @var FileService
     */
    private $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    public function getOutputPath(Index $indexFile): string
    {
        return $this->replaceCount(
            $indexFile->getRule(),
            $this->replaceFileEnding($indexFile, $indexFile->getRule()->getMoveFilename())
        );
    }

    public function moveFile(Index $indexedFile): void
    {
    }

    private function replaceCount(Rule $rule, string $filename): string
    {
        return str_replace('{COUNT}', $rule->getCount(), $filename);
    }

    private function replaceFileEnding(Index $indexFile, string $filename): string
    {
        return str_replace(
            '{FILE_ENDING}',
            $this->fileService->getFileEnding($indexFile->getInputPath()),
            $filename
        );
    }
}
