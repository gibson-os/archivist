<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy\Audible;

use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Session;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Core\Service\PriorityInterface;
use GibsonOS\Module\Archivist\Exception\BrowserException;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Service\BrowserService;
use GibsonOS\Module\Archivist\Strategy\AudibleStrategy;

class OtpStrategy implements AudibleStrategyInterface, PriorityInterface
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
        $parameters = $account->getExecutionParameters();
        $this->browserService->fillFormFields(
            $session,
            [AudibleStrategy::KEY_OTP => $parameters[AudibleStrategy::KEY_OTP]],
        );
        $session->getPage()->pressButton('auth-signin-button');
        unset($parameters[AudibleStrategy::KEY_OTP]);
        $account->setExecutionParameters($parameters);
        $this->browserService->waitForLoaded($session);

        return false;
    }

    public function supports(Session $session): bool
    {
        return $session->getPage()->findById('otpCode') !== null;
    }

    public function getPriority(): int
    {
        return 80;
    }

    public function getExecuteParameters(Session $session, Account $account): array
    {
        $parameters = $account->getExecutionParameters();

        if (isset($parameters[AudibleStrategy::KEY_OTP])) {
            return [];
        }

        return [AudibleStrategy::KEY_OTP => new StringParameter('OTP')];
    }
}
