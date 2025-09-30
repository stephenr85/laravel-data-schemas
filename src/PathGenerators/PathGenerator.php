<?php

namespace Rushing\LaravelDataSchemas\PathGenerators;

use ReflectionClass;

interface PathGenerator
{
    public function getSchemaPath(ReflectionClass $class): string;
}