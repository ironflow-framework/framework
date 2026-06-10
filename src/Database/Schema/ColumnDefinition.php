<?php

declare(strict_types=1);

namespace Core\Database\Schema;

/**
 * Fluent column definition for Blueprint.
 */
class ColumnDefinition
{
    private array $options;

    public function __construct(
        private readonly Blueprint $blueprint,
        public readonly string $name,
        public readonly string $type,
        array $options = []
    ) {
        $this->options = $options;
    }

    public function nullable(bool $value = true): static
    {
        $this->options['notnull'] = !$value;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->options['default'] = $value;
        return $this;
    }

    public function unsigned(): static
    {
        $this->options['unsigned'] = true;
        return $this;
    }

    public function length(int $length): static
    {
        $this->options['length'] = $length;
        return $this;
    }

    public function unique(): static
    {
        $this->blueprint->unique($this->name);
        return $this;
    }

    public function index(): static
    {
        $this->blueprint->index($this->name);
        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
