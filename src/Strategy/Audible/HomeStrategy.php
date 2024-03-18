<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy\Audible;

use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Session;
use GibsonOS\Core\Service\PriorityInterface;
use GibsonOS\Module\Archivist\Exception\BrowserException;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Service\BrowserService;
use GibsonOS\Module\Archivist\Strategy\AudibleStrategy;

class HomeStrategy implements AudibleStrategyInterface, PriorityInterface
{
    public function __construct(private readonly BrowserService $browserService)
    {
    }

    /**
     * @throws ElementNotFoundException
     * @throws BrowserException
     */
    public function execute(Session $session, Account $account): bool
    {
        $page = $session->getPage();
        $page->clickLink('Anmelden');
        $this->browserService->waitForLoaded($session);

        return false;
    }

    public function supports(Session $session): bool
    {
        return $session->getCurrentUrl() === AudibleStrategy::URL;
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function getExecuteParameters(Session $session, Account $account): array
    {
        return [];
    }
}
