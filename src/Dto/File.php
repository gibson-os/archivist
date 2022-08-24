<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Dto;

use DateTimeInterface;
use GibsonOS\Module\Archivist\Model\Account;

class File implements \JsonSerializable
{
    private ?int $length = null;

    /**
     * @var resource|null
     */
    private $resource;

    public function __construct(
        private readonly string $name,
        private readonly string $path,
        private readonly DateTimeInterface $createDate,
        private readonly Account $account
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getCreateDate(): DateTimeInterface
    {
        return $this->createDate;
    }

    public function getAccount(): Account
    {
        return $this->account;
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
            'account' => $this->getAccount()->getId(),
        ];
    }
}
