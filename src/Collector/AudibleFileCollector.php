<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Collector;

use Generator;
use GibsonOS\Core\Service\DateTimeService;
use GibsonOS\Module\Archivist\Dto\Audible\TitleParts;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Strategy\AudibleStrategy;
use Psr\Log\LoggerInterface;

class AudibleFileCollector
{
    private const EXPRESSION_NOT_PODCAST = 'href="([^"]*)"[^<]*<[^<]*<[^<]*<[^<]*<[^<]*Herunterladen.+?</a>.+?';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly DateTimeService $dateTimeService,
    ) {
    }

    public function getFilesFromPage(Account $account, string $content): Generator
    {
        $pageParts = explode('class="adbl-library-content-row"', $content);
        $expression =
            'bc-size-headline3"\s*>([^<]*).+?(Serie:.+?<a[^>]*>([^<]*)</a>(\s*,.+?Titel (\S*))?.+?)?' .
            self::EXPRESSION_NOT_PODCAST .
            'bc-spacing-top-base'
        ;

        foreach ($pageParts as $pagePart) {
            $this->logger->debug(sprintf('Search #%s# in %s', $expression, $pagePart));
            $matches = ['', '', '', '', '', '', '', '', '', ''];

            if (preg_match('#' . $expression . '#s', $pagePart, $matches) !== 1) {
                continue;
            }

            $titleParts = new TitleParts(trim($matches[1]), trim($matches[3]), trim($matches[5]));
            $series = $titleParts->getSeries();

            if (empty($series)) {
                $this->findSeriesAndEpisode($titleParts);
            }

            $title = $this->cleanTitle($titleParts);
            $this->logger->info(sprintf('Find %s', $title));

            yield new File($title, AudibleStrategy::URL . $matches[6], $this->dateTimeService->get(), $account);
        }

        yield null;
    }

    private function findSeriesAndEpisode(TitleParts $titleParts): void
    {
        $splitTitle = explode(':', str_ireplace(['staffel'], '', $titleParts->getTitle()));

        if (count($splitTitle) !== 2) {
            return;
        }

        $matches = ['', '', ''];

        if (preg_match('/(.*)\s([\d\W]?\d)\s*$/', $splitTitle[1], $matches) !== 1) {
            return;
        }

        $titleParts->setSeries(trim($matches[1]) ?: trim($splitTitle[0]));
        $titleParts->setEpisode(trim($matches[2]));
    }

    private function cleanTitle(TitleParts $titleParts): string
    {
        $cleanTitle = $titleParts->getTitle();
        $cleanTitleParts = explode(':', $cleanTitle);
        $series = $titleParts->getSeries();

        if (
            !empty($series)
            && count($cleanTitleParts) === 2
            && mb_stripos($cleanTitleParts[0], $series) === 0
            && mb_stripos($cleanTitleParts[1], $series) === false
        ) {
            $cleanTitle = $cleanTitleParts[1] . ':' . $cleanTitleParts[0];
        }

        $episode = $titleParts->getEpisode();
        $cleanTitle = preg_replace(
            '/(\b)(' . preg_quote($series, '/') . '|' . preg_quote($episode, '/') . ')(\b)/i',
            '$1$3',
            $cleanTitle,
        );

        if (!empty($series)) {
            $cleanTitle = preg_replace('/:.*/s', '', $cleanTitle);
        }

        $cleanTitle = trim($cleanTitle);

        if (empty($cleanTitle)) {
            $cleanTitle = $series;
        }

        $cleanTitle = preg_replace('/^[-:._]*/', '', $cleanTitle);
        $cleanTitle = preg_replace('/[-:._]*$/', '', $cleanTitle);
        $cleanTitle = preg_replace('/:/', ' - ', $cleanTitle);
        $cleanTitle = str_replace('/', ' ', $cleanTitle);
        $cleanTitle = preg_replace('/\s\.\s/', ' ', $cleanTitle);

        if (!empty($episode)) {
            $cleanTitle = $episode . ' ' . $cleanTitle;
        }

        return
            (empty($series) ? '' : '[' . trim($series) . '] ') .
            trim(preg_replace('/\s{2,}/s', ' ', $cleanTitle))
        ;
    }
}
