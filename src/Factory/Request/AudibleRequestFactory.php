<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Factory\Request;

use GibsonOS\Core\Dto\Web\Request;

class AudibleRequestFactory
{
    public function getRequest(string $url, string $cookiesJar): Request
    {
        $cookieFileName = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('cookies') . '.jar';
        file_put_contents($cookieFileName, $cookiesJar);

        return (new Request($url))
            ->setCookieFile($cookieFileName)
        ;
    }
}
