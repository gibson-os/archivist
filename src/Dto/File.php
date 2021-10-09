<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Dto;

use DateTimeInterface;

class File implements \JsonSerializable
{
    private ?int $length = null;

    /**
     * @var resource|null
     */
    private $resource;

    public function __construct(private string $name, private string $path, private DateTimeInterface $createDate, private Strategy $strategy)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): File
    {
        $this->name = $name;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): File
    {
        $this->path = $path;

        return $this;
    }

    public function getCreateDate(): DateTimeInterface
    {
        return $this->createDate;
    }

    public function setCreateDate(DateTimeInterface $createDate): File
    {
        $this->createDate = $createDate;

        return $this;
    }

    public function getStrategy(): Strategy
    {
        return $this->strategy;
    }

    public function setStrategy(Strategy $strategy): File
    {
        $this->strategy = $strategy;

        return $this;
    }

    /**
     * @param resource $resource
     */
    public function setResource($resource, int $length): File
    {
        $this->resource = $resource;
        $this->length = $length;

        return $this;
    }

    public function getLength(): ?int
    {
        return $this->length;
    }

    /**
     * @return resource|null
     */
    public function getResource()
    {
        return $this->resource;
    }

    public function jsonSerialize(): array
    {
        return [
            'path' => $this->getPath(),
            'name' => $this->getName(),
            'createDate' => $this->getCreateDate()->format('Y-m-d H:i:s'),
            'strategy' => $this->getStrategy()->getName(),
        ];
    }
}
