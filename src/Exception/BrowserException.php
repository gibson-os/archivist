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
        $screenshotFilename = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('BrowserScreenshot', true) . '.png';
        $pageFilename = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('BrowserPage', true) . '.html';

        try {
            file_put_contents($pageFilename, $session->getPage()->getContent());
            $message .= ' | Page: ' . $pageFilename;
            file_put_contents($screenshotFilename, $session->getScreenshot());
            $message .= ' | Screenshot: ' . $screenshotFilename;
        } catch (Throwable $exception) {
            // do nothing
        }

        parent::__construct($message);
    }
}
