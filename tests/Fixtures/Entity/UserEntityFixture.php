<?php

declare(strict_types=1);

namespace Bpzr\Tests\Fixtures\Entity;

use Bpzr\Tests\Fixtures\Enum\UserTypeEnum;
use DateTimeImmutable;

readonly class UserEntityFixture
{
    public function __construct(
        private int $id,
        private ?string $username,
        private string $password,
        private bool $isSubscriber,
        private float $averageOrderPrice,
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
}