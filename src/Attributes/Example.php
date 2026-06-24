<?php

namespace Rushing\LaravelDataSchemas\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Example
{
    public function __construct(public mixed $value) {}
}
