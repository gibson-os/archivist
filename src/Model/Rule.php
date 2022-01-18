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
use GibsonOS\Core\Utility\JsonUtility;
use GibsonOS\Module\Archivist\Strategy\StrategyInterface;
use JsonSerializable;

/**
 * @method User    getUser()
 * @method Rule    setUser(User $user)
 * @method Index[] getIndexed()
 * @method Rule    addIndexed(Index[] $indexed)
 * @method Rule    setIndexed(Index[] $indexed)
 */
#[Table]
#[Key(unique: true, columns: ['strategy', 'observed_filename'])]
class Rule extends AbstractModel implements JsonSerializable
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

    private ?string $strategyName = null;

    #[Column(type: Column::TYPE_JSON)]
    private string $configuration = '[]';

    #[Column(length: 255)]
    private ?string $observedFilename = null;

    #[Column(length: 255)]
    private string $moveDirectory;

    #[Column(length: 255)]
    private string $moveFilename;

    #[Column]
    #[Key]
    private bool $active = false;

    #[Column(type: Column::TYPE_TEXT)]
    private ?string $message = null;

    #[Column(attributes: [Column::ATTRIBUTE_UNSIGNED])]
    private int $userId;

    #[Column]
    private ?DateTimeImmutable $lastRun = null;

    #[Constraint]
    protected User $user;

    /**
     * @var Index[]
     */
    #[Constraint('rule', Index::class)]
    protected array $indexed = [];

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
    public function setStrategy(string $strategy): Rule
    {
        $this->strategy = $strategy;

        return $this;
    }

    public function setStrategyByClass(StrategyInterface $strategy): Rule
    {
        $this->strategy = $strategy::class;
        $this->strategyName = $strategy->getName();

        return $this;
    }

    public function getStrategyName(): ?string
    {
        return $this->strategyName;
    }

    public function setStrategyName(?string $strategyName): Rule
    {
        $this->strategyName = $strategyName;

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

    public function getLastRun(): ?DateTimeImmutable
    {
        return $this->lastRun;
    }

    public function setLastRun(?DateTimeImmutable $lastRun): Rule
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
            'strategyName' => $this->getStrategyName(),
            'configuration' => JsonUtility::decode($this->getConfiguration()),
            'observedFilename' => $this->getObservedFilename(),
            'moveDirectory' => $this->getMoveDirectory(),
            'moveFilename' => $this->getMoveFilename(),
            'active' => $this->isActive(),
            'message' => $this->getMessage(),
            'lastRun' => $this->getLastRun()?->format('Y-m-d H:i:s'),
        ];
    }
}
