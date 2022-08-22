<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Model;

use DateTimeImmutable;
use GibsonOS\Core\Attribute\Install\Database\Column;
use GibsonOS\Core\Attribute\Install\Database\Constraint;
use GibsonOS\Core\Attribute\Install\Database\Key;
use GibsonOS\Core\Attribute\Install\Database\Table;
use GibsonOS\Core\Model\AbstractModel;
use GibsonOS\Core\Model\User;
use JsonSerializable;

/**
 * @method User    getUser()
 * @method Account setUser(User $user)
 * @method Rule[]  getRules()
 * @method Account addRules(Rule[] $indexed)
 * @method Account setRules(Rule[] $indexed)
 */
#[Table]
#[Key(unique: true, columns: ['strategy', 'observed_filename'])]
class Account extends AbstractModel implements JsonSerializable
{
    #[Column(attributes: [Column::ATTRIBUTE_UNSIGNED], autoIncrement: true)]
    private ?int $id = null;

    #[Column(length: 128)]
    private string $name;

    /**
     * @var class-string
     */
    #[Column(length: 255)]
    private string $strategy;

    #[Column(type: Column::TYPE_JSON)]
    private array $configuration = [];

    #[Column(type: Column::TYPE_TEXT)]
    private ?string $message = null;

    #[Column]
    #[Key]
    private bool $active = false;

    #[Column(attributes: [Column::ATTRIBUTE_UNSIGNED])]
    private int $userId;

    #[Column]
    private ?DateTimeImmutable $lastRun = null;

    #[Constraint]
    protected User $user;

    /**
     * @var Rule[]
     */
    #[Constraint('account', Rule::class)]
    protected array $rules = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Account
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): Account
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return class-string
     */
    public function getStrategy(): string
    {
        return $this->strategy;
    }

    /**
     * @param class-string $strategy
     */
    public function setStrategy(string $strategy): Account
    {
        $this->strategy = $strategy;

        return $this;
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function setConfiguration(array $configuration): Account
    {
        $this->configuration = $configuration;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): Account
    {
        $this->message = $message;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): Account
    {
        $this->active = $active;

        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): Account
    {
        $this->userId = $userId;

        return $this;
    }

    public function getLastRun(): ?DateTimeImmutable
    {
        return $this->lastRun;
    }

    public function setLastRun(?DateTimeImmutable $lastRun): Account
    {
        $this->lastRun = $lastRun;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'strategy' => $this->getStrategy(),
            'configuration' => $this->getConfiguration(),
            'message' => $this->getMessage(),
            'active' => $this->isActive(),
            'lastRun' => $this->getLastRun()?->format('Y-m-d H:i:s'),
        ];
    }
}
