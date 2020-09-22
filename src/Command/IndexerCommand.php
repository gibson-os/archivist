<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Command;

use GibsonOS\Core\Command\AbstractCommand;
use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Exception\GetError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Service\DirService;
use GibsonOS\Module\Archivist\Repository\RuleRepository;

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

    public function __construct(RuleRepository $ruleRepository, DirService $dirService)
    {
        $this->ruleRepository = $ruleRepository;
        $this->dirService = $dirService;
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

            foreach ($this->dirService->getFiles($directory, $rule->getObserveFilename() ?? '*') as $file) {
                echo $file . PHP_EOL;
            }
        }

        return 0;
    }
}
