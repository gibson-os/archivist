<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy\Audible;

use Behat\Mink\Session;
use GibsonOS\Core\Service\PriorityInterface;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Service\BrowserService;

class LibraryStrategy implements AudibleStrategyInterface, PriorityInterface
{
    public function __construct(private readonly BrowserService $browserService)
    {
    }

    public function execute(Session $session, Account $account): bool
    {
        $session->getPage()->clickLink('Bibliothek');
        $this->browserService->waitForElementById($session, 'lib-subheader-actions');

        return true;
    }

    public function supports(Session $session): bool
    {
        return $session->getPage()->hasLink('Bibliothek');
    }

    public function getExecuteParameters(Session $session, Account $account): array
    {
        return [];
    }

    public function getPriority(): int
    {
        return 10;
    }
}
