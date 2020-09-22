<?php
declare(strict_types=1);

namespace GibsonOS\Module\Scan\Model;

use GibsonOS\Core\Model\AbstractModel;
use GibsonOS\Core\Model\User;

class Rule extends AbstractModel
{
    /**
     * @var int|null
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $observeDirectory;

    /**
     * @var string|null
     */
    private $observeFilename;

    /**
     * @var string|null
     */
    private $moveDirectory;

    /**
     * @var string|null
     */
    private $moveFilename;

    /**
     * @var bool
     */
    private $active = false;

    /**
     * @var int
     */
    private $count = 0;

    /**
     * @var int
     */
    private $userId;

    /**
     * @var User
     */
    private $user;

    /**
     * @var Index[]
     */
    private $indexed = [];

    public static function getTableName(): string
    {
        return 'archivist_rule';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Rule
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): Rule
    {
        $this->name = $name;

        return $this;
    }

    public function getObserveDirectory(): string
    {
        return $this->observeDirectory;
    }

    public function setObserveDirectory(string $observeDirectory): Rule
    {
        $this->observeDirectory = $observeDirectory;

        return $this;
    }

    public function getObserveFilename(): ?string
    {
        return $this->observeFilename;
    }

    public function setObserveFilename(?string $observeFilename): Rule
    {
        $this->observeFilename = $observeFilename;

        return $this;
    }

    public function getMoveDirectory(): ?string
    {
        return $this->moveDirectory;
    }

    public function setMoveDirectory(?string $moveDirectory): Rule
    {
        $this->moveDirectory = $moveDirectory;

        return $this;
    }

    public function getMoveFilename(): ?string
    {
        return $this->moveFilename;
    }

    public function setMoveFilename(?string $moveFilename): Rule
    {
        $this->moveFilename = $moveFilename;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): Rule
    {
        $this->active = $active;

        return $this;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): Rule
    {
        $this->count = $count;

        return $this;
    }

    /**
     * @return Index[]
     */
    public function getIndexed(): array
    {
        return $this->indexed;
    }

    /**
     * @param Index[] $indexed
     */
    public function setIndexed(array $indexed): Rule
    {
        $this->indexed = $indexed;

        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): Rule
    {
        $this->userId = $userId;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): Rule
    {
        $this->user = $user;

        return $this;
    }
}
