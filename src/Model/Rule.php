<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Model;

use GibsonOS\Core\Attribute\Install\Database\Column;
use GibsonOS\Core\Attribute\Install\Database\Constraint;
use GibsonOS\Core\Attribute\Install\Database\Key;
use GibsonOS\Core\Attribute\Install\Database\Table;
use GibsonOS\Core\Model\AbstractModel;

/**
 * @method Account getAccount()
 * @method Rule    setAccount(Account $account)
 * @method Index[] getIndexed()
 * @method Rule    addIndexed(Index[] $indexed)
 * @method Rule    setIndexed(Index[] $indexed)
 */
#[Table]
#[Key(unique: true, columns: ['account_id', 'observed_filename', 'observed_content'])]
class Rule extends AbstractModel implements \JsonSerializable
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

    #[Column(attributes: [Column::ATTRIBUTE_UNSIGNED])]
    private int $accountId;

    #[Column]
    private bool $active = false;

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

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): Rule
    {
        $this->active = $active;

        return $this;
    }

    public function getAccountId(): int
    {
        return $this->accountId;
    }

    public function setAccountId(int $accountId): Rule
    {
        $this->accountId = $accountId;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'observedFilename' => $this->getObservedFilename(),
            'observedContent' => $this->getObservedContent(),
            'moveDirectory' => $this->getMoveDirectory(),
            'moveFilename' => $this->getMoveFilename(),
            'active' => $this->isActive(),
        ];
    }
}
