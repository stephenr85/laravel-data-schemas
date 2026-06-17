<?php

namespace Rushing\LaravelDataSchemas\Attributes;

use Attribute;

/**
 * Declares the JSON Schema item type for a scalar array property (e.g. string[]),
 * so the generator can emit `items` — required by strict structured-output providers.
 * For arrays of Data objects use Spatie's #[DataCollectionOf] instead.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class ArrayItems
{
    public function __construct(public string $type) {}
}
