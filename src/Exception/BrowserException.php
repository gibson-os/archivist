<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Exception;

use Behat\Mink\Session;
use GibsonOS\Core\Exception\AbstractException;
use Throwable;

class BrowserException extends AbstractException
{
    public function __construct($message, Session $session)
    {
        $filename = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('BrowserScreenshot', true);

        try {
            file_put_contents($filename, $session->getScreenshot());
            $message .= ' | Screenshot: ' . $filename;
        } catch (Throwable $exception) {
            // do nothing
        }

        parent::__construct($message);
    }
}
