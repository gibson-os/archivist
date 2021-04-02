<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Service;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Session;
use DMore\ChromeDriver\ChromeDriver;

class BrowserService
{
    private Session $session;

    public function __construct()
    {
        $this->session = new Session(new ChromeDriver('http://localhost:9222', null, ''));
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
