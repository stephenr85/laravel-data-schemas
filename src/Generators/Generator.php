<?php

namespace Rushing\LaravelDataSchemas\Generators;

use ReflectionClass;

interface Generator
{
    public function canGenerate(ReflectionClass $class): bool;

    public function generate(ReflectionClass $class): array;
}