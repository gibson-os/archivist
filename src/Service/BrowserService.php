<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Service;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Session;
use DMore\ChromeDriver\ChromeDriver;
use GibsonOS\Module\Archivist\Exception\BrowserException;

class BrowserService
{
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
        if ($maxWait <= 0) {
            throw new BrowserException(sprintf('Element %s not found!', $id));
        }

        $waitTime = 100000;

        try {
            $element = $page->findById($id);

            if ($element === null) {
                usleep($waitTime);

                $element = $this->waitForElementById($page, $id, $maxWait - $waitTime);
            }

            return $element;
        } catch (DriverException $exception) {
            usleep($waitTime);

            return $this->waitForElementById($page, $id, $maxWait - $waitTime);
        }
    }

    /**
     * @param array<string, string> $parameters
     */
    public function fillFormFields(DocumentElement $page, array $parameters): void
    {
        foreach ($parameters as $name => $value) {
            $page->findField($name)->setValue($value);
        }
    }

    /**
     * @throws DriverException
     */
    public function getCookies(Session $session): array
    {
        /** @var ChromeDriver $driver */
        $driver = $session->getDriver();

        return $driver->getCookies();
    }

    public function setCookies(Session $session, array $cookies): void
    {
        /** @var ChromeDriver $driver */
        $driver = $session->getDriver();

        foreach ($cookies as $cookie) {
            $driver->setCookie($cookie['name'], $cookie['value']);
        }
    }
}