<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class DateTimeFormat
{
    public function __construct(private string $format)
    {
    }

    public function getFormat(): string
    {
        return $this->format;
    }
}