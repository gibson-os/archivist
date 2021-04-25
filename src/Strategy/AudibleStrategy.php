<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Generator;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;

class AudibleStrategy extends AbstractWebStrategy
{
    public function getName(): string
    {
        return 'Audible';
    }

    public function getConfigurationParameters(Strategy $strategy): array
    {
        return [
            'email' => (new StringParameter('E-Mail'))->setInputType(StringParameter::INPUT_TYPE_EMAIL),
            'password' => (new StringParameter('Passwort'))->setInputType(StringParameter::INPUT_TYPE_PASSWORD),
        ];
    }

    public function saveConfigurationParameters(Strategy $strategy, array $parameters): bool
    {
        return true;
    }

    public function getFiles(Strategy $strategy): Generator
    {
        // TODO: Implement getFiles() method.
    }

    public function setFileResource(File $file): File
    {
        // https://www.voss.earth/2018/08/01/audible-dateien-befreien/
        // ffprobe.exe file.aax
        // ffmpeg -activation_bytes 9736d71d -i file.aax -map 0:a -vn file.mp3
        // TODO: Implement setFileResource() method.
    }

    public function unload(Strategy $strategy): void
    {
        $session = $this->getSession($strategy);
        $page = $session->getPage();
        $page->clickLink('Abmelden');
        $session->stop();
    }
}
