<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Model;

use GibsonOS\Core\Model\ModelInterface;
use GibsonOS\Module\Archivist\Exception\StrategyException;

/**
 * Fake Model.
 */
class Strategy implements ModelInterface
{
    private string $id;

    private string $name;

    private string $parameters;

    /**
     * @throws StrategyException
     */
    public static function getTableName(): string
    {
        throw new StrategyException('Strategy model can not be persist!');
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): Strategy
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): Strategy
    {
        $this->name = $name;

        return $this;
    }

    public function getParameters(): string
    {
        return $this->parameters;
    }

    public function setParameters(string $parameters): Strategy
    {
        $this->parameters = $parameters;

        return $this;
    }
}
