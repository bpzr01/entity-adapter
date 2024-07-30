<?php

declare(strict_types=1);

namespace Bpzr\Tests\Fixture\Entity;

readonly class UnsupportedDataTypeEntityFixture
{
    public function __construct(
        private string $name,
        private int $count,
    ) {
    }
}