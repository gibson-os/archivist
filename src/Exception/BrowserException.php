<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Exception;

use GibsonOS\Core\Exception\AbstractException;
use GibsonOS\Module\Archivist\Service\BrowserService;

class BrowserException extends AbstractException
{
    public function __construct($message, BrowserService $browserService)
    {
        $filename = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('BrowserScreenshot', true);
        file_put_contents($filename, $browserService->getScreenshot());

        parent::__construct($message . ' | Screenshot: ' . $filename);
    }
}
