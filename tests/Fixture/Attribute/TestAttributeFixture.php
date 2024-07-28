<?php

declare(strict_types=1);

namespace Bpzr\Tests\Fixture\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
readonly class TestAttributeFixture
{
    public function __construct(private int $testValue)
    {
    }

    public function getTestValue(): int
    {
        return $this->testValue;
    }
}