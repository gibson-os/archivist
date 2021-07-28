<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Exception;

use GibsonOS\Core\Exception\AbstractException;
use GibsonOS\Module\Archivist\Service\BrowserService;
use Throwable;

class BrowserException extends AbstractException
{
    public function __construct($message, BrowserService $browserService)
    {
        $filename = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('BrowserScreenshot', true);

        try {
            file_put_contents($filename, $browserService->getScreenshot());
            $message .= ' | Screenshot: ' . $filename;
        } catch (Throwable $exception) {
            // do nothing
        }

        parent::__construct($message);
    }
}
