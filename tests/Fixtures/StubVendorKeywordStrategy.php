<?php

namespace Rushing\LaravelDataSchemas\Tests\Fixtures;

use ReflectionProperty;
use Rushing\LaravelDataSchemas\Strategies\SchemaStrategy;
use Rushing\LaravelDataSchemas\Strategies\SchemaStrategyContext;

/**
 * A test strategy that tags every property with a vendor keyword, standing in
 * for downstream contributors like content-engine's generation attributes.
 */
class StubVendorKeywordStrategy implements SchemaStrategy
{
    public function apply(ReflectionProperty $property, array $schema, SchemaStrategyContext $context): array
    {
        $schema['x-demo'] = 'tagged';

        return $schema;
    }
}
