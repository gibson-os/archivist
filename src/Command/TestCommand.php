<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Command;

use GibsonOS\Core\Command\AbstractCommand;
use GibsonOS\Core\Dto\Web\Request;
use GibsonOS\Core\Service\WebService;
use GibsonOS\Module\Archivist\Service\BrowserService;
use Psr\Log\LoggerInterface;

class TestCommand extends AbstractCommand
{
    private BrowserService $browserService;

    private WebService $webService;

    public function __construct(LoggerInterface $logger, BrowserService $browserService, WebService $webService)
    {
        parent::__construct($logger);
        $this->browserService = $browserService;
        $this->webService = $webService;
    }

    protected function run(): int
    {
        $session = $this->browserService->getSession();
        $page = $this->browserService->loadPage($session, 'https://wolli.dyndns.tv');

        $page->findField('username')->setValue('wolli');
        $page->findField('password')->setValue('qgABUQbB');
        $page->pressButton('Login');
        $this->browserService->waitForElementById($page, 'desktopContainer');

        $this->webService->get(
            (new Request('https://wolli.dyndns.tv'))->setCookieFile($this->browserService->createCookieFile($session))
        );
        $session->stop();

        return 0;
    }
}
