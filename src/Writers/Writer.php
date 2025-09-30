<?php

namespace Rushing\LaravelDataSchemas\Writers;

use Rushing\LaravelDataSchemas\Support\SchemaCollection;

interface Writer
{
    public function write(SchemaCollection $collection): void;
}