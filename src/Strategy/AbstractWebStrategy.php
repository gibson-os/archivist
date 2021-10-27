<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Behat\Mink\Session;
use GibsonOS\Core\Service\CryptService;
use GibsonOS\Core\Service\DateTimeService;
use GibsonOS\Core\Service\WebService;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Exception\StrategyException;
use GibsonOS\Module\Archivist\Service\BrowserService;
use Psr\Log\LoggerInterface;

abstract class AbstractWebStrategy implements StrategyInterface
{
    protected const KEY_SESSION = 'session';

    public function __construct(protected BrowserService $browserService, protected WebService $webService, protected LoggerInterface $logger, protected CryptService $cryptService, protected DateTimeService $dateTimeService)
    {
    }

    /**
     * @throws StrategyException
     */
    public function getResponseValue(string $response, string $key, string $value, string $valueKey): string
    {
        $matches = [];
        preg_match(
            '/' . $key . '="' . preg_quote($value, '/') . '"[^>]*' . $valueKey . '="([^"]*)"/',
            $response,
            $matches
        );

        if (!isset($matches[1])) {
            throw new StrategyException(
                'Value for ' . $valueKey . ' on element with ' . $key . ' "' . $value . '" not found!'
            );
        }

        $this->logger->debug(
            'Get value "' . $matches[1] . '" for "' . $valueKey . '" on element with ' . $key . ' "' . $value . '"'
        );

        return $matches[1];
    }

    protected function getSession(Strategy $strategy = null): Session
    {
        if (
            $strategy !== null &&
            $strategy->hasConfigurationValue(self::KEY_SESSION)
        ) {
            return unserialize($strategy->getConfigurationValue(self::KEY_SESSION));
        }

        return $this->browserService->getSession();
    }
}
