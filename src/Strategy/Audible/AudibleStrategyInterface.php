<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy\Audible;

use Behat\Mink\Session;
use GibsonOS\Module\Archivist\Model\Account;

interface AudibleStrategyInterface
{
    public function execute(Session $session, Account $account): bool;

    public function supports(Session $session): bool;

    public function getExecuteParameters(Session $session, Account $account): array;
}
