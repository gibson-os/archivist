<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Model;

use DateTimeImmutable;
use GibsonOS\Core\Attribute\Install\Database\Column;
use GibsonOS\Core\Attribute\Install\Database\Constraint;
use GibsonOS\Core\Attribute\Install\Database\Key;
use GibsonOS\Core\Attribute\Install\Database\Table;
use GibsonOS\Core\Model\AbstractModel;
use JsonSerializable;

/**
 * @method Account getAccount()
 * @method Rule    setAccount(Account $account)
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

    #[Column(length: 255)]
    private ?string $observedFilename = null;

    #[Column(length: 255)]
    private ?string $observedContent = null;

    #[Column(length: 255)]
    private string $moveDirectory;

    #[Column(length: 255)]
    private string $moveFilename;

    #[Column]
    private ?DateTimeImmutable $lastRun = null;

    #[Constraint]
    protected Account $account;

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

    public function getObservedFilename(): ?string
    {
        return $this->observedFilename;
    }

    public function setObservedFilename(?string $observedFilename): Rule
    {
        $this->observedFilename = $observedFilename;

        return $this;
    }

    public function getObservedContent(): ?string
    {
        return $this->observedContent;
    }

    public function setObservedContent(?string $observedContent): Rule
    {
        $this->observedContent = $observedContent;

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
            'observedFilename' => $this->getObservedFilename(),
            'moveDirectory' => $this->getMoveDirectory(),
            'moveFilename' => $this->getMoveFilename(),
            'lastRun' => $this->getLastRun()?->format('Y-m-d H:i:s'),
        ];
    }
}
