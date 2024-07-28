<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Table
{
    public function __construct(private string $name)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }
}