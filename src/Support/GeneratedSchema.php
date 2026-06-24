<?php

namespace Rushing\LaravelDataSchemas\Support;

class GeneratedSchema
{
    public function __construct(
        public readonly string $className,
        public readonly string $outputPath,
        public readonly array $schema,
    ) {}

    public function getPropertyCount(): int
    {
        return count($this->schema['properties'] ?? []);
    }
}
