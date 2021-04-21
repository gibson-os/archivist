<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Model;

use GibsonOS\Core\Exception\DateTimeError;
use GibsonOS\Core\Model\AbstractModel;
use GibsonOS\Core\Model\User;
use GibsonOS\Core\Utility\JsonUtility;
use JsonSerializable;

class Rule extends AbstractModel implements JsonSerializable
{
    private ?int $id = null;

    private string $name;

    private string $strategy;

    private string $configuration = '[]';

    private ?string $observedFilename = null;

    private string $moveDirectory = '';

    private string $moveFilename = '';

    private bool $active = false;

    private ?string $message = null;

    private int $userId;

    private User $user;

    /**
     * @var Index[]
     */
    private array $indexed = [];

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

    public function getStrategy(): string
    {
        return $this->strategy;
    }

    public function setStrategy(string $strategy): Rule
    {
        $this->strategy = $strategy;

        return $this;
    }

    public function getConfiguration(): string
    {
        return $this->configuration;
    }

    public function setConfiguration(string $configuration): Rule
    {
        $this->configuration = $configuration;

        return $this;
    }

    public function getObservedFilename(): ?string
    {
        return $this->observedFilename;
    }

    public function setObservedFilename(?string $observedFilename): Rule
    {
        $this->observedFilename = $observedFilename;

        return $this;
    }

    public function getMoveDirectory(): string
    {
        return $this->moveDirectory;
    }

    public function setMoveDirectory(string $moveDirectory): Rule
    {
        $this->moveDirectory = $moveDirectory;

        return $this;
    }

    public function getMoveFilename(): string
    {
        return $this->moveFilename;
    }

    public function setMoveFilename(string $moveFilename): Rule
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

    public function addIndex(Index $index): Rule
    {
        $this->indexed[] = $index;

        return $this;
    }

    /**
     * @throws DateTimeError
     */
    public function loadIndexed()
    {
        /** @var Index[] $indexed */
        $indexed = $this->loadForeignRecords(
            Index::class,
            $this->getId(),
            Index::getTableName(),
            'rule_id'
        );

        $this->setIndexed($indexed);
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): Rule
    {
        $this->message = $message;

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

    /**
     * @throws DateTimeError
     */
    public function getUser(): User
    {
        $this->loadForeignRecord($this->user, $this->getUserId());

        return $this->user;
    }

    public function setUser(User $user): Rule
    {
        $this->user = $user;
        $this->setUserId((int) $user->getId());

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'strategy' => $this->getStrategy(),
            'configuration' => JsonUtility::decode($this->getConfiguration()),
            'observedFilename' => $this->getObservedFilename(),
            'moveDirectory' => $this->getMoveDirectory(),
            'moveFilename' => $this->getMoveFilename(),
            'active' => $this->isActive(),
            'message' => $this->getMessage(),
        ];
    }
}
