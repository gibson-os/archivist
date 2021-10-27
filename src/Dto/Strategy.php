<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Dto;

use GibsonOS\Core\Dto\Parameter\AbstractParameter;
use GibsonOS\Core\Model\AutoCompleteModelInterface;
use JsonSerializable;

class Strategy implements JsonSerializable, AutoCompleteModelInterface
{
    private array $configuration = [];

    private int $configurationStep = 0;

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

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function setConfiguration(array $configuration): Strategy
    {
        $this->configuration = $configuration;

        return $this;
    }

    public function getConfigurationValue(string $name)
    {
        return $this->configuration[$name];
    }

    public function hasConfigurationValue(string $name): bool
    {
        return isset($this->configuration[$name]);
    }

    public function setConfigurationValue(string $name, $value): Strategy
    {
        $this->configuration[$name] = $value;

        return $this;
    }

    public function getConfigurationStep(): int
    {
        return $this->configurationStep;
    }

    public function setConfigurationStep(int $configurationStep): Strategy
    {
        $this->configurationStep = $configurationStep;

        return $this;
    }

    public function setNextConfigurationStep(): Strategy
    {
        ++$this->configurationStep;

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
            'configuration' => $this->getConfiguration(),
            'parameters' => $this->getParameters(),
            'configurationStep' => $this->getConfigurationStep(),
        ];
    }

    public function getAutoCompleteId(): string
    {
        return $this->getClassName();
    }
}
