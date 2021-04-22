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
                'FALSE', // include subdomain
                $cookie['path'],
                $cookie['secure'] ? 'TRUE' : 'FALSE',
                $cookie['expires'],
                $cookie['name'],
                $cookie['value'],
            ];

            $cookies .= implode("\t", $cookie) . PHP_EOL;
        }

        $cookieFileName = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cookies' . uniqid() . '.jar';
        file_put_contents($cookieFileName, $cookies);

        return $cookieFileName;
    }
}
