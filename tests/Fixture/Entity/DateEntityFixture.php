<?php

declare(strict_types=1);

namespace Bpzr\Tests\Fixture\Entity;

use Bpzr\EntityAdapter\Attribute\DateTimeFormat;
use DateTimeImmutable;

readonly class DateEntityFixture
{
    public function __construct(
        #[DateTimeFormat('Y-m-d')]
        private DateTimeImmutable $date,
        #[DateTimeFormat('Y')]
        private DateTimeImmutable $year,
        #[DateTimeFormat('Y-m-d H:i:s')]
        private DateTimeImmutable $dateTime,
    ) {
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function getYear(): DateTimeImmutable
    {
        return $this->year;
    }

    public function getDateTime(): DateTimeImmutable
    {
        return $this->dateTime;
    }
}