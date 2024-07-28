<?php

declare(strict_types=1);

namespace Bpzr\Tests\Fixture\Entity;

use Bpzr\Tests\Fixture\Attribute\TestAttributeFixture;

class SingleAttributeEntityFixture
{
    public function __construct(
        private string $firstName,
        #[TestAttributeFixture(123)]
        private int $orderCount,
    ) {
    }
}