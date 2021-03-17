<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Dto;

use GibsonOS\Core\Model\AutoCompleteModelInterface;

class Strategy implements AutoCompleteModelInterface
{
    private string $name;

    private string $className;

    private array $config = [];

    public function __construct(string $name, string $className)
    {
        $this->name = $name;
        $this->className = $className;
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

    public function setConfigValue(string $name, $value): Strategy
    {
        $this->config[$name] = $value;

        return $this;
    }

    public function getAutoCompleteId(): string
    {
        return $this->getClassName();
    }
}
