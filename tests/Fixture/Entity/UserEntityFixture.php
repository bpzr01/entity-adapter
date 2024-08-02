<?php

declare(strict_types=1);

namespace Bpzr\Tests\Fixture\Entity;

use Bpzr\EntityAdapter\Attribute\Contingent;
use Bpzr\EntityAdapter\Attribute\DateTimeFormat;
use Bpzr\EntityAdapter\Attribute\Table;
use Bpzr\Tests\Fixture\Enum\UserTypeEnum;
use DateTimeImmutable;

#[Table('user_123')]
readonly class UserEntityFixture
{
    public function __construct(
        #[Contingent]
        private ?int $id,
        private ?string $username,
        private string $password,
        private bool $isSubscriber,
        private float $averageOrderPrice,
        #[DateTimeFormat('Y-m-d H:i:s')]
        private DateTimeImmutable $registeredAt,
        private UserTypeEnum $userType,
        private string $config,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function isSubscriber(): bool
    {
        return $this->isSubscriber;
    }

    public function getAverageOrderPrice(): float
    {
        return $this->averageOrderPrice;
    }

    public function getRegisteredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function getUserType(): UserTypeEnum
    {
        return $this->userType;
    }

    public function getConfig(): string
    {
        return $this->config;
    }
}