<?php

declare(strict_types=1);

namespace Bpzr\Tests\Fixture\Entity;

use Bpzr\EntityAdapter\Attribute\Table;
use Bpzr\Tests\Fixture\Attribute\OtherTestAttributeFixture;

#[Table('product')]
#[OtherTestAttributeFixture('hello')]
readonly class ProductEntityFixture
{
    public function __construct(
        private int $id,
        private bool $isPurchasable,
        private string $config,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function isPurchasable(): bool
    {
        return $this->isPurchasable;
    }

    public function getConfig(): string
    {
        return $this->config;
    }
}