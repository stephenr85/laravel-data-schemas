<?php

namespace Rushing\LaravelDataSchemas\Generators;

use ReflectionClass;

interface Generator
{
    public function canGenerate(ReflectionClass $class): bool;

    public function generate(ReflectionClass $class): array;

    /**
     * Return a variant configured to emit a request-flavoured schema.
     *
     * The request/response divergence is a deferred seam (see ADR-0027); the
     * default implementation collapses to a single variant.
     */
    public function forRequest(): static;

    /**
     * Return a variant configured to emit a response-flavoured schema.
     */
    public function forResponse(): static;

    /**
     * Set the generation mode explicitly. Known modes: collapsed, request, response.
     */
    public function mode(string $mode): static;
}
