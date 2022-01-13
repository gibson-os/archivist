<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Model;

use DateTimeImmutable;
use DateTimeInterface;
use GibsonOS\Core\Attribute\Install\Database\Column;
use GibsonOS\Core\Attribute\Install\Database\Table;
use GibsonOS\Core\Model\AbstractModel;
use mysqlDatabase;

#[Table]
class Index extends AbstractModel
{
    #[Column(attributes: [Column::ATTRIBUTE_UNSIGNED], autoIncrement: true)]
    private ?int $id = null;

    #[Column(length: 512)]
    private string $inputPath;

    #[Column(length: 512)]
    private ?string $outputPath = null;

    #[Column(attributes: [Column::ATTRIBUTE_UNSIGNED])]
    private int $size = 0;

    #[Column]
    private ?int $ruleId = null;

    #[Column(type: Column::TYPE_TEXT)]
    private ?string $error = null;

    #[Column(default: Column::DEFAULT_CURRENT_TIMESTAMP)]
    private DateTimeInterface $changed;

    private ?Rule $rule = null;

    public function __construct(mysqlDatabase $database = null)
    {
        $this->setChanged(new DateTimeImmutable());

        parent::__construct($database);
    }

    public static function getTableName(): string
    {
        return 'archivist_index';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Index
    {
        $this->id = $id;

        return $this;
    }

    public function getInputPath(): string
    {
        return $this->inputPath;
    }

    public function setInputPath(string $inputPath): Index
    {
        $this->inputPath = $inputPath;

        return $this;
    }

    public function getOutputPath(): ?string
    {
        return $this->outputPath;
    }

    public function setOutputPath(?string $outputPath): Index
    {
        $this->outputPath = $outputPath;

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): Index
    {
        $this->size = $size;

        return $this;
    }

    public function getRuleId(): ?int
    {
        return $this->ruleId;
    }

    public function setRuleId(?int $ruleId): Index
    {
        $this->ruleId = $ruleId;

        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): Index
    {
        $this->error = $error;

        return $this;
    }

    public function getChanged(): DateTimeInterface
    {
        return $this->changed;
    }

    public function setChanged(DateTimeInterface $changed): Index
    {
        $this->changed = $changed;

        return $this;
    }

    public function getRule(): ?Rule
    {
        $ruleId = $this->getRuleId();

        if ($ruleId !== null) {
            if ($this->rule === null) {
                $this->rule = new Rule();
            }

            $this->loadForeignRecord($this->rule, $ruleId);
        }

        return $this->rule;
    }

    public function setRule(?Rule $rule): Index
    {
        $this->rule = $rule;
        $this->setRuleId($rule instanceof Rule ? $rule->getId() : null);

        return $this;
    }
}
