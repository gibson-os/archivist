<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Dto;

use GibsonOS\Core\Dto\Parameter\AbstractParameter;
use GibsonOS\Core\Model\AutoCompleteModelInterface;
use JsonSerializable;

class Strategy implements JsonSerializable, AutoCompleteModelInterface
{
    private array $config = [];

    private int $configStep = 0;

    /**
     * @var AbstractParameter[]
     */
    private array $parameters = [];

    public function __construct(private string $name, private string $className)
    {
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

    public function getClassName(): string
    {
        return $this->className;
    }

    public function setClassName(string $className): Strategy
    {
        $this->className = $className;

        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): Strategy
    {
        $this->config = $config;

        return $this;
    }

    public function getConfigValue(string $name)
    {
        return $this->config[$name];
    }

    public function hasConfigValue(string $name): bool
    {
        return isset($this->config[$name]);
    }

    public function setConfigValue(string $name, $value): Strategy
    {
        $this->config[$name] = $value;

        return $this;
    }

    public function getConfigStep(): int
    {
        return $this->configStep;
    }

    public function setConfigStep(int $configStep): Strategy
    {
        $this->configStep = $configStep;

        return $this;
    }

    public function setNextConfigStep(): Strategy
    {
        ++$this->configStep;

        return $this;
    }

    /**
     * @return AbstractParameter[]
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @param AbstractParameter[] $parameters
     */
    public function setParameters(array $parameters): Strategy
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'className' => $this->getClassName(),
            'name' => $this->getName(),
            'config' => $this->getConfig(),
            'parameters' => $this->getParameters(),
            'configStep' => $this->getConfigStep(),
        ];
    }

    public function getAutoCompleteId(): string
    {
        return $this->getClassName();
    }
}
