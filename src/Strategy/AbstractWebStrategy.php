<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use GibsonOS\Core\Service\WebService;
use GibsonOS\Module\Archivist\Exception\StrategyException;
use Psr\Log\LoggerInterface;

abstract class AbstractWebStrategy implements StrategyInterface
{
    protected WebService $webService;

    protected LoggerInterface $logger;

    public function __construct(WebService $webService, LoggerInterface $logger)
    {
        $this->webService = $webService;
        $this->logger = $logger;
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
