<?php

declare(strict_types=1);

namespace Bpzr\Tests\Fixture\Entity;

use DateTimeImmutable;

readonly class NullablePropertiesEntityFixture
{
    public function __construct(
        private ?int $age,
        private ?string $name,
        private ?DateTimeImmutable $nextBirthday,
    ) {
    }
}