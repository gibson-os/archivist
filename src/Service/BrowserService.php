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

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function getPage(Session $session): DocumentElement
    {
        return $session->getPage();
    }

    public function getSession(): Session
    {
        return new Session(new ChromeDriver('http://localhost:9222', null, 'chrome://new-tab-page'));
    }

    /**
     * @throws BrowserException
     */
    public function loadPage(Session $session, string $url, ?string $waitId = null): DocumentElement
    {
        $session->visit($url);

        if ($waitId !== null) {
            $this->waitForElementById($session, $waitId);
        }

        return $this->getPage($session);
    }

    /**
     * @throws BrowserException
     */
    public function waitForElementById(Session $session, string $id, int $maxWait = 10000000): NodeElement
    {
        try {
            $this->waitFor(
                $session,
                function () use (&$element, $session, $id, $maxWait) {
                    $element = $session->getPage()->findById($id);

                    if ($element === null) {
                        usleep(self::WAIT_TIME);

                        $element = $this->waitForElementById($session, $id, $maxWait - self::WAIT_TIME);
                    }
                },
                $maxWait,
            );
        } catch (BrowserException) {
            throw new BrowserException(\sprintf('Element #%s not found!', $id), $session);
        }

        return $element;
    }

    /**
     * @throws BrowserException
     */
    public function waitForLink(Session $session, string $link, int $maxWait = 10000000): NodeElement
    {
        try {
            $this->waitFor(
                $session,
                function () use (&$element, $session, $link, $maxWait) {
                    $element = $session->getPage()->findLink($link);

                    if ($element === null) {
                        usleep(self::WAIT_TIME);

                        $element = $this->waitForLink($session, $link, $maxWait - self::WAIT_TIME);
                    }
                },
                $maxWait,
            );
        } catch (BrowserException) {
            throw new BrowserException(\sprintf('Link "%s" not found!', $link), $session);
        }

        return $element;
    }

    /**
     * @throws BrowserException
     */
    public function waitForButton(Session $session, string $button, int $maxWait = 10000000): NodeElement
    {
        try {
            $this->waitFor(
                $session,
                function () use (&$element, $session, $button, $maxWait) {
                    $element = $session->getPage()->findButton($button);

                    if ($element === null) {
                        usleep(self::WAIT_TIME);

                        $element = $this->waitForButton($session, $button, $maxWait - self::WAIT_TIME);
                    }
                },
                $maxWait,
            );
        } catch (BrowserException) {
            throw new BrowserException(\sprintf('Button "%s" not found!', $button), $session);
        }

        return $element;
    }

    /**
     * @throws BrowserException
     */
    public function waitFor(Session $session, callable $waitFunction, int $maxWait = 10000000): void
    {
        if ($maxWait <= 0) {
            throw new BrowserException('Max wait time reached!', $session);
        }

        try {
            $waitFunction();
        } catch (DriverException) {
            usleep(self::WAIT_TIME);

            $this->waitFor($session, $waitFunction, $maxWait - self::WAIT_TIME);
        }
    }

    /**
     * @param array<string, string> $parameters
     *
     * @throws BrowserException
     */
    public function fillFormFields(Session $session, array $parameters): void
    {
        $page = $session->getPage();

        foreach ($parameters as $name => $value) {
            $field = $page->findField($name);

            if ($field === null) {
                throw new BrowserException(\sprintf('Field %s not found!', $name), $session);
            }

            $field->focus();
            $field->setValue($value);
            $this->logger->info(\sprintf('Fill field "%s" with "%s"', $name, $value));
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
        $this->logger->info(\sprintf('Write cookies to %s', $cookieFileName));
        $this->logger->debug($cookies);

        return $cookieFileName;
    }

    public function goto(Session $session, string $uri): void
    {
        $session->executeScript('window.location.href = "' . $uri . '"');
    }
}
