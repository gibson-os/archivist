<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Model;

use DateTimeImmutable;
use DateTimeInterface;
use GibsonOS\Core\Model\AbstractModel;
use mysqlDatabase;

class Index extends AbstractModel
{
    private ?int $id = null;

    private string $inputPath;

    private ?string $outputPath = null;

    private int $size = 0;

    private ?int $ruleId = null;

    private ?string $error = null;

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
