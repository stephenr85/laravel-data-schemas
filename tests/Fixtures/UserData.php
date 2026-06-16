<?php

namespace Rushing\LaravelDataSchemas\Tests\Fixtures;

use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
