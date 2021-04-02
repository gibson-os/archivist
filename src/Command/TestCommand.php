<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Command;

use GibsonOS\Core\Command\AbstractCommand;
use GibsonOS\Module\Archivist\Service\BrowserService;
use Psr\Log\LoggerInterface;

class TestCommand extends AbstractCommand
{
    private BrowserService $browserService;

    public function __construct(LoggerInterface $logger, BrowserService $browserService)
    {
        parent::__construct($logger);
        $this->browserService = $browserService;
    }

    protected function run(): int
    {
        $page = $this->browserService->getPage('https://wolli.dyndns.tv');

        $page->findField('username')->setValue('wolli');
        $page->findField('password')->setValue('qgABUQbB');
        $page->findField('password')->keyPress(13);

        errlog($page->getContent());

        return 0;
    }
}
