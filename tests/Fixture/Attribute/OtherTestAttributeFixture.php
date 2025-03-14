<?php

declare(strict_types=1);

namespace Bpzr\Tests\Fixture\Attribute;

use Attribute;

#[Attribute]
readonly class OtherTestAttributeFixture
{
    public function __construct(private string $greeting)
    {
    }
}