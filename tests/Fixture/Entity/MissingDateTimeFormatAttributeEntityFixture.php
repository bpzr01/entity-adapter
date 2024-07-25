<?php

declare(strict_types=1);

namespace Bpzr\Tests\Fixture\Entity;

use DateTimeImmutable;

readonly class MissingDateTimeFormatAttributeEntityFixture
{
    public function __construct(
        private int $id,
        private DateTimeImmutable $date,
        private string $name,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function getName(): string
    {
        return $this->name;
    }
}