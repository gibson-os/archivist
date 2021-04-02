<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use GibsonOS\Core\Service\CryptService;
use GibsonOS\Module\Archivist\Exception\StrategyException;
use GibsonOS\Module\Archivist\Service\BrowserService;
use Psr\Log\LoggerInterface;

abstract class AbstractWebStrategy implements StrategyInterface
{
    protected BrowserService $browserService;

    protected LoggerInterface $logger;

    protected CryptService $cryptService;

    public function __construct(BrowserService $browserService, LoggerInterface $logger, CryptService $cryptService)
    {
        $this->browserService = $browserService;
        $this->logger = $logger;
        $this->cryptService = $cryptService;
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
}
