<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Generator;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Model\Rule;

class AudibleStrategy extends AbstractWebStrategy
{
    private const URL = 'http://localhost/img/page/audible/';

//    private const URL = 'https://audible.de';

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
        $session = $this->browserService->getSession();
        $page = $this->browserService->loadPage($session, self::URL);
        $page->clickLink('Bereits Kunde? Anmelden');
        $this->browserService->waitForElementById($page, 'ap_email');
        $this->browserService->fillFormFields($page, $parameters);
        $page->pressButton('signInSubmit');
        $this->browserService->waitForElementById($page, 'adbl-web-carousel-c1');
        $page->clickLink('Bibliothek');
        $this->browserService->waitForElementById($page, 'lib-subheader-actions');

        return true;
    }

    public function getFiles(Strategy $strategy, Rule $rule): Generator
    {
        $session = $this->getSession($strategy);
        $page = $session->getPage();

        $matches = [[], [], [], [], [], []];
        preg_match_all(
            '/class="adbl-library-content-row".+?bc-size-headline3">([^<]*).+?(Serie.+?<a[^>]*>([^<]*)<\/a>(, Titel (\S*))?.+?)?summaryLabel(.+?)(adbl-lib-action-download[^<]*<a[^<]*href="([^"]*)"[^<]*<[^<]*<[^<]*Herunterladen.+?<\/a>.+?)?bc-spacing-top-base/s',
            $page->getContent(),
            $matches
        );

        foreach ($matches[0] as $id => $match) {
            $title = $match[1];
//            $filename =
        }
        yield new File('foo', 'bar', $this->dateTimeService->get(), $strategy);
    }

    public function setFileResource(File $file): File
    {
        // https://www.voss.earth/2018/08/01/audible-dateien-befreien/
        // ffprobe.exe file.aax
        // ffmpeg -activation_bytes 9736d71d -i file.aax -map 0:a -vn file.mp3
        // TODO: Implement setFileResource() method.

        return $file;
    }

    public function unload(Strategy $strategy): void
    {
        $session = $this->getSession($strategy);
        $page = $session->getPage();
        $page->clickLink('Abmelden');
        $session->stop();
    }

    public function getLockName(Rule $rule): string
    {
        return 'audible';
    }
}
