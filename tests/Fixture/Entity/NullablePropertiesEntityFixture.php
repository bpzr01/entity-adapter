<?php

declare(strict_types=1);

namespace Bpzr\Tests\Fixture\Entity;

use Bpzr\EntityAdapter\Attribute\Table;
use DateTimeImmutable;

#[Table('nullable_properties')]
readonly class NullablePropertiesEntityFixture
{
    public function __construct(
        private ?int $age,
        private ?string $name,
        private ?DateTimeImmutable $nextBirthday,
    ) {
    }
}