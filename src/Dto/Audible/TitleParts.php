<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Dto\Audible;

class TitleParts
{
    private string $title;

    private string $series;

    private string $episode;

    public function __construct(string $title, string $series, string $episode)
    {
        $this->title = $title;
        $this->series = $series;
        $this->episode = $episode;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getSeries(): string
    {
        return $this->series;
    }

    public function setSeries(string $series): void
    {
        $this->series = $series;
    }

    public function getEpisode(): string
    {
        return $this->episode;
    }

    public function setEpisode(string $episode): void
    {
        $this->episode = $episode;
    }
}
