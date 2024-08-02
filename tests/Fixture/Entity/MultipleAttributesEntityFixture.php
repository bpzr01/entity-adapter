<?php

declare(strict_types=1);

namespace Bpzr\Tests\Fixture\Entity;

use Bpzr\EntityAdapter\Attribute\Table;
use Bpzr\Tests\Fixture\Attribute\TestAttributeFixture;

#[Table('multiple_attributes')]
class MultipleAttributesEntityFixture
{
    public function __construct(
        private string $lastName,
        #[TestAttributeFixture(1)]
        #[TestAttributeFixture(2)]
        #[TestAttributeFixture(3)]
        #[TestAttributeFixture(4)]
        private int $productCount,
    ) {
    }
}