<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Command;

use Behat\Mink\Session;
use GibsonOS\Core\Command\AbstractCommand;
use GibsonOS\Module\Archivist\Exception\BrowserException;
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
        $session = $this->browserService->getSession();
        //        $page = $this->browserService->loadPage($session, 'https://wollis-page.de/');
        $page = $this->browserService->loadPage($session, 'https://check24.tessa-cloud.de/');
        $this->browserService->waitForElementById($page, 'ext-element-107');
        $page->findField('password')->setValue('Hau$b00t');
        $page->findField('userName')->setValue('benjamin.wollenweber@check24.de');
//        $session->executeScript('document.getElementById("ext-element-55").value = "Hau$b00t"');
        $page->pressButton('ext-element-107');
        file_put_contents('image.png', $session->getScreenshot());

        try {
            $element = $this->browserService->waitForElementById($page, 'ext-element-211');
        } catch (BrowserException $e) {
            file_put_contents('image2.png', $session->getScreenshot());

            throw $e;
        }

        if (trim($element->getText()) !== 'Neueste Dokumente') {
            throw new StrategyException('Login failed!');
        }

        errlog($page->getContent());
        $session->stop();

        return 0;
        $session = $this->browserService->getSession();
        $page = $this->browserService->loadPage($session, 'https://wolli.dyndns.tv');

        $page->findField('username')->setValue('wolli');
        $page->findField('password')->setValue('qgABUQbB');
        $page->pressButton('Login');
        $this->browserService->waitForElementById($page, 'desktopContainer');

        file_put_contents('session', serialize($session));
        sleep(10);
        errlog('get session from file');
        /** @var Session $session */
        $session = unserialize(file_get_contents('session'));
        errlog($session->getPage()->getContent());
        $session->stop();

        return 0;
    }
}
