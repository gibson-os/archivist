<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Exception;

use Behat\Mink\Session;
use GibsonOS\Core\Exception\AbstractException;

class BrowserException extends AbstractException
{
    public function __construct($message, private Session $session)
    {
        parent::__construct($message);
    }

    public function getSession(): Session
    {
        return $this->session;
    }
}
