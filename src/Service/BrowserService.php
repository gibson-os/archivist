<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Service;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Session;
use DMore\ChromeDriver\ChromeDriver;
use GibsonOS\Module\Archivist\Exception\BrowserException;
use Psr\Log\LoggerInterface;

class BrowserService
{
    private const WAIT_TIME = 100000;

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getSession(): Session
    {
        return new Session(new ChromeDriver('http://localhost:9222', null, ''));
    }

    /**
     * @throws BrowserException
     */
    public function loadPage(Session $session, string $url, string $waitId = null): DocumentElement
    {
        $session->visit($url);
        $page = $session->getPage();

        if ($waitId !== null) {
            $this->waitForElementById($page, $waitId);
        }

        return $page;
    }

    /**
     * @throws BrowserException
     */
    public function waitForElementById(DocumentElement $page, string $id, int $maxWait = 10000000): NodeElement
    {
        try {
            $this->waitFor(
                $page,
                function () use (&$element, $page, $id, $maxWait) {
                    $element = $page->findById($id);

                    if ($element === null) {
                        usleep(self::WAIT_TIME);

                        $element = $this->waitForElementById($page, $id, $maxWait - self::WAIT_TIME);
                    }
                },
                $maxWait
            );
        } catch (BrowserException $e) {
            throw new BrowserException(sprintf('Element #%s not found!', $id));
        }

        return $element;
    }

    /**
     * @throws BrowserException
     */
    public function waitForLink(DocumentElement $page, string $link, int $maxWait = 10000000): NodeElement
    {
        try {
            $this->waitFor(
                $page,
                function () use (&$element, $page, $link, $maxWait) {
                    $element = $page->findLink($link);

                    if ($element === null) {
                        usleep(self::WAIT_TIME);

                        $element = $this->waitForLink($page, $link, $maxWait - self::WAIT_TIME);
                    }
                },
                $maxWait
            );
        } catch (BrowserException $e) {
            throw new BrowserException(sprintf('Link "%s" not found!', $link));
        }

        return $element;
    }

    /**
     * @throws BrowserException
     */
    public function waitForButton(DocumentElement $page, string $button, int $maxWait = 10000000): NodeElement
    {
        try {
            $this->waitFor(
                $page,
                function () use (&$element, $page, $button, $maxWait) {
                    $element = $page->findButton($button);

                    if ($element === null) {
                        usleep(self::WAIT_TIME);

                        $element = $this->waitForButton($page, $button, $maxWait - self::WAIT_TIME);
                    }
                },
                $maxWait
            );
        } catch (BrowserException $e) {
            throw new BrowserException(sprintf('Button "%s" not found!', $button));
        }

        return $element;
    }

    /**
     * @throws BrowserException
     */
    public function waitFor(DocumentElement $page, callable $waitFunction, int $maxWait = 10000000): void
    {
        if ($maxWait <= 0) {
            throw new BrowserException('Max wait time reached!');
        }

        try {
            $waitFunction();
        } catch (DriverException $exception) {
            usleep(self::WAIT_TIME);

            $this->waitFor($page, $waitFunction, $maxWait - self::WAIT_TIME);
        }
    }

    /**
     * @param array<string, string> $parameters
     *
     * @throws BrowserException
     */
    public function fillFormFields(DocumentElement $page, array $parameters): void
    {
        foreach ($parameters as $name => $value) {
            $field = $page->findField($name);

            if ($field === null) {
                throw new BrowserException(sprintf('Field %s not found!', $name));
            }

            $field->focus();
            $field->setValue($value);
            $this->logger->info(sprintf('Fill field "%s" with "%s"', $name, $value));
        }
    }

    /**
     * @throws DriverException
     */
    public function createCookieFile(Session $session): string
    {
        /** @var ChromeDriver $driver */
        $driver = $session->getDriver();
        $cookies = '';

        foreach ($driver->getCookies() as $cookie) {
            $cookie = [
                $cookie['domain'],
                mb_strpos($cookie['domain'], '.') === 0 ? 'TRUE' : 'FALSE',
                $cookie['path'],
                $cookie['secure'] ? 'TRUE' : 'FALSE',
                (int) $cookie['expires'] === -1 ? 0 : $cookie['expires'],
                $cookie['name'],
                $cookie['value'],
            ];

            $cookies .= implode("\t", $cookie) . PHP_EOL;
        }

        $cookieFileName = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cookies' . uniqid() . '.jar';
        file_put_contents($cookieFileName, $cookies);
        $this->logger->info(sprintf('Write cookies to %s', $cookieFileName));
        $this->logger->debug($cookies);

        return $cookieFileName;
    }

    public function goto(Session $session, string $uri): void
    {
        $session->executeScript('window.location.href = "' . $uri . '"');
    }
}
