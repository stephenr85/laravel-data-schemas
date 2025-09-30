<?php

namespace Rushing\LaravelDataSchemas\Support;

use Illuminate\Support\Collection;

class SchemaCollection extends Collection
{
    public function add(GeneratedSchema $schema): self
    {
        $this->items[] = $schema;

        return $this;
    }
}