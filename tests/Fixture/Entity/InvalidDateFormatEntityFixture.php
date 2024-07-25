<?php

declare(strict_types=1);

namespace Bpzr\Tests\Fixture\Entity;

use Bpzr\EntityAdapter\Attribute\DateTimeFormat;
use DateTimeImmutable;

class InvalidDateFormatEntityFixture
{
    public function __construct(
        #[DateTimeFormat('j/n/Y')]
        private DateTimeImmutable $date,
    ) {
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }
}