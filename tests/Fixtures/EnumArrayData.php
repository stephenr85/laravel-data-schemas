<?php

namespace Rushing\LaravelDataSchemas\Tests\Fixtures;

use Rushing\LaravelDataSchemas\Attributes\ArrayItems;
use Spatie\LaravelData\Data;

class EnumArrayData extends Data
{
    /**
     * @param  list<string>  $statuses  A list of enum-valued scalars.
     */
    public function __construct(
        #[ArrayItems(StatusEnum::class)]
        public array $statuses,
    ) {}
}
