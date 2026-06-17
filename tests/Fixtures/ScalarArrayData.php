<?php

namespace Rushing\LaravelDataSchemas\Tests\Fixtures;

use Rushing\LaravelDataSchemas\Attributes\ArrayItems;
use Spatie\LaravelData\Data;

class ScalarArrayData extends Data
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        #[ArrayItems('string')]
        public array $tags,
    ) {}
}
