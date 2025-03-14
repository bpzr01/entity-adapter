<?php

declare(strict_types=1);

namespace Bpzr\Tests\Fixture\Entity;

use Bpzr\EntityAdapter\Attribute\Table;
use Bpzr\Tests\Fixture\Attribute\OtherTestAttributeFixture;
use Bpzr\Tests\Fixture\Attribute\TestAttributeFixture;

#[Table('single_attribute')]
class SingleAttributeEntityFixture
{
    public function __construct(
        private string $firstName,
        #[TestAttributeFixture(123)]
        #[OtherTestAttributeFixture('hello')]
        private int $orderCount,
    ) {
    }
}