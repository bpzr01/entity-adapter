<?php

declare(strict_types=1);

namespace Bpzr\Tests\Fixture\Entity;

use UnitEnum;

readonly class UnsupportedDataTypeEntityFixture
{
    public function __construct(
        private UnitEnum $enum,
        private string $type,
    ) {
    }

    public function getEnum(): UnitEnum
    {
        return $this->enum;
    }

    public function getType(): string
    {
        return $this->type;
    }
}