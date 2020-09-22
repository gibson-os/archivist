<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Command;

use GibsonOS\Core\Command\AbstractCommand;
use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Exception\GetError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Service\DirService;
use GibsonOS\Module\Archivist\Model\Index;
use GibsonOS\Module\Archivist\Repository\IndexRepository;
use GibsonOS\Module\Archivist\Repository\RuleRepository;
use GibsonOS\Module\Archivist\Service\RuleService;

class IndexerCommand extends AbstractCommand
{
    /**
     * @var RuleRepository
     */
    private $ruleRepository;

    /**
     * @var DirService
     */
    private $dirService;

    /**
     * @var IndexRepository
     */
    private $indexRepository;

    /**
     * @var RuleService
     */
    private $ruleService;

    public function __construct(
        RuleRepository $ruleRepository,
        IndexRepository $indexRepository,
        DirService $dirService,
        RuleService $ruleService
    ) {
        $this->ruleRepository = $ruleRepository;
        $this->dirService = $dirService;
        $this->indexRepository = $indexRepository;
        $this->ruleService = $ruleService;
    }

    /**
     * @throws DateTimeError
     * @throws GetError
     * @throws SelectError
     */
    protected function run(): int
    {
        $scannedDirectories = [];

        foreach ($this->ruleRepository->getAll() as $rule) {
            $directory = $this->dirService->addEndSlash($rule->getObserveDirectory());

            if (isset($scannedDirectories[$directory . $rule->getObserveFilename()])) {
                continue;
            }

            foreach ($this->dirService->getFiles($directory, $rule->getObserveFilename() ?? '*') as $filename) {
                try {
                    $indexedFile = $this->indexRepository->getByInputPath($filename);
                } catch (DateTimeError | SelectError $e) {
                    $indexedFile = (new Index())
                        ->setRule($rule->isActive() ? $rule : null)
                        ->setInputPath($filename)
                    ;
                    $indexedFile->setOutputPath($this->ruleService->getOutputPath($indexedFile));
                }

                $size = filesize($filename);

                if (
                    $size !== 0 &&
                    $size === $indexedFile->getSize()
                ) {
                }

                $indexedFile
                    ->setSize($size)
                    ->save()
                ;

                echo $indexedFile->getOutputPath() . PHP_EOL;
            }
        }

        return 0;
    }
}
