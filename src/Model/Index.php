<?php
declare(strict_types=1);

namespace GibsonOS\Module\Scan\Model;

use DateTimeInterface;
use GibsonOS\Core\Model\AbstractModel;

class Index extends AbstractModel
{
    /**
     * @var int|null
     */
    private $id;

    /**
     * @var string
     */
    private $inputPath;

    /**
     * @var string
     */
    private $outputPath;

    /**
     * @var int
     */
    private $size = 0;

    /**
     * @var bool
     */
    private $new = true;

    /**
     * @var bool
     */
    private $deleted = false;

    /**
     * @var int|null
     */
    private $ruleId;

    /**
     * @var DateTimeInterface
     */
    private $added;

    /**
     * @var Rule|null
     */
    private $rule;

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

    public function getOutputPath(): string
    {
        return $this->outputPath;
    }

    public function setOutputPath(string $outputPath): Index
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

    public function isNew(): bool
    {
        return $this->new;
    }

    public function setNew(bool $new): Index
    {
        $this->new = $new;

        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): Index
    {
        $this->deleted = $deleted;

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

    public function getAdded(): DateTimeInterface
    {
        return $this->added;
    }

    public function setAdded(DateTimeInterface $added): Index
    {
        $this->added = $added;

        return $this;
    }

    public function getRule(): ?Rule
    {
        return $this->rule;
    }

    public function setRule(?Rule $rule): Index
    {
        $this->rule = $rule;

        return $this;
    }
}
