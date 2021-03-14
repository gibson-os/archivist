<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Dto;

class Strategy
{
    private string $className;

    private array $config = [];

    public function __construct(string $className)
    {
        $this->className = $className;
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
}
