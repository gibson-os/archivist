<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Dto\Audible;

class TitleParts
{
    public function __construct(private string $title, private string $series, private string $episode)
    {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): TitleParts
    {
        $this->title = $title;

        return $this;
    }

    public function getSeries(): string
    {
        return $this->series;
    }

    public function setSeries(string $series): TitleParts
    {
        $this->series = $series;

        return $this;
    }

    public function getEpisode(): string
    {
        return $this->episode;
    }

    public function setEpisode(string $episode): TitleParts
    {
        $this->episode = $episode;

        return $this;
    }
}
