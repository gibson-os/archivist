<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy\Audible;

use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Session;
use GibsonOS\Core\Service\CryptService;
use GibsonOS\Core\Service\PriorityInterface;
use GibsonOS\Module\Archivist\Exception\BrowserException;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Service\BrowserService;
use GibsonOS\Module\Archivist\Strategy\AudibleStrategy;

class EmailStrategy implements AudibleStrategyInterface, PriorityInterface
{
    public function __construct(
        private readonly BrowserService $browserService,
        private readonly CryptService $cryptService,
    ) {
    }

    /**
     * @throws ElementNotFoundException
     * @throws BrowserException
     */
    public function execute(Session $session, Account $account): bool
    {
        $configuration = $account->getConfiguration();
        $email = $this->cryptService->decrypt($configuration[AudibleStrategy::KEY_EMAIL]);

        $this->browserService->fillFormFields($session, [AudibleStrategy::KEY_EMAIL => $email]);
        $session->getPage()->pressButton('continue');
        $this->browserService->waitForLoaded($session);

        return false;
    }

    public function supports(Session $session): bool
    {
        return $session->getPage()->findById('ap_email') !== null;
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getExecuteParameters(Session $session, Account $account): array
    {
        return [];
    }
}
