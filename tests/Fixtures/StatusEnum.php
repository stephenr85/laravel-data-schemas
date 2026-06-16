<?php

namespace Rushing\LaravelDataSchemas\Tests\Fixtures;

enum StatusEnum: string
{
    case Draft = 'draft';
    case Published = 'published';
}
