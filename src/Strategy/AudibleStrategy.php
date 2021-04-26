<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Generator;
use GibsonOS\Core\Dto\Parameter\OptionParameter;
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
            'elements' => new OptionParameter('Elemente', [
                'Einzelne Hörbücher' => 'single',
                'Serien' => 'series',
                'Podcast' => 'podcast',
            ]),
        ];
    }

    public function saveConfigurationParameters(Strategy $strategy, array $parameters): bool
    {
        $session = $this->browserService->getSession();
        $page = $this->browserService->loadPage($session, self::URL);
        $page->clickLink('Bereits Kunde? Anmelden');
        $this->browserService->waitForElementById($page, 'ap_email');
        $this->browserService->fillFormFields($page, ['email' => $parameters['email'], 'password' => $parameters['password']]);
        $page->pressButton('signInSubmit');
        $this->browserService->waitForElementById($page, 'adbl-web-carousel-c1');
        $page->clickLink('Bibliothek');
        $this->browserService->waitForElementById($page, 'lib-subheader-actions');

        $strategy
            ->setConfigValue('session', serialize($session))
            ->setConfigValue('elements', $parameters['elements'])
            ->setConfigValue('email', $this->cryptService->encrypt($parameters['email']))
            ->setConfigValue('password', $this->cryptService->encrypt($parameters['password']))
        ;

        return true;
    }

    public function getFiles(Strategy $strategy, Rule $rule): Generator
    {
        $expression = 'adbl-lib-action-download[^<]*<a[^<]*href="([^"]*)"[^<]*<[^<]*<[^<]*Herunterladen.+?<\/a>.+?';

        if ($strategy->getConfigValue('elements') === 'podcast') {
            $expression = 'adbl-episodes-link[^<]*<a[^<]*href="([^"]*)"[^<]*chevron-container.+?<\/a>.+?';
        }

        $session = $this->getSession($strategy);
        $page = $session->getPage();

        $pageParts = explode('class="adbl-library-content-row"', $page->getContent());

        // @todo seiten einbauen
        foreach ($pageParts as $pagePart) {
            $matches = ['', '', '', '', '', '', ''];
            preg_match(
                '/bc-size-headline3">([^<]*).+?(Serie.+?<a[^>]*>([^<]*)<\/a>(, Titel (\S*))?.+?)?summaryLabel.+?' .
                $expression .
                'bc-spacing-top-base/s',
                $pagePart,
                $matches
            );
            if (empty($matches)) {
                continue;
            }

            $titleParts = [
                'title' => $matches[1],
                'series' => $matches[3],
                'episode' => $matches[5],
            ];

            if (empty($titleParts['series'])) {
                $this->findSeriesAndEpisode($titleParts);
            }

            if (
                empty($titleParts['series']) &&
                $strategy->getConfigValue('elements') === 'series'
            ) {
                continue;
            }

            yield new File($this->cleanTitle($titleParts), '', $this->dateTimeService->get(), $strategy);
        }
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

    private function findSeriesAndEpisode(array &$titleParts): void
    {
        $splitTitle = explode(':', $titleParts['title']);

        if (count($splitTitle) !== 2) {
            return;
        }

        $matches = ['', '', ''];
        preg_match('/(.+?)([\d|\W]*)$/', $splitTitle[1], $matches);
        $titleParts['series'] = trim($matches[1]);
        $titleParts['episode'] = trim($matches[2]);
    }

    private function cleanTitle(array $titleParts): string
    {
        $cleanTitle = str_ireplace([$titleParts['series'], $titleParts['episode']], '', $titleParts['title']);

        if (!empty($titleParts['series'])) {
            $cleanTitle = preg_replace('/:.*/s', '', $cleanTitle);
        }

        $cleanTitle = trim($cleanTitle);

        if (empty($cleanTitle)) {
            $cleanTitle = $titleParts['series'];
        }

        if (!empty($titleParts['episode'])) {
            $cleanTitle = $titleParts['episode'] . ' ' . $cleanTitle;
        }

        $cleanTitle = str_replace([':', '.'], '', $cleanTitle);

        return
            (empty($titleParts['series']) ? '' : '[' . $titleParts['series'] . '] ') .
            trim(preg_replace('/\s{2,}/s', ' ', $cleanTitle))
        ;
    }
}
