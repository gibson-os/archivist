<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Service;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Session;
use DMore\ChromeDriver\ChromeDriver;

class BrowserService
{
    private Session $session;

    public function __construct(ChromeDriver $chromeDriver)
    {
        $this->session = new Session($chromeDriver);
        $this->session->start();
    }

    public function getPage($url): DocumentElement
    {
        $this->session->visit($url);

        return $this->session->getPage();
    }

    public function resetSession(): void
    {
        $this->session->restart();
    }
}
