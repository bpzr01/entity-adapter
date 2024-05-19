<?php

declare(strict_types=1);

namespace Bpzr\Tests\Fixture\Entity;

class NonConstructibleEntityFixture
{
    public int $id;
    public string $username;
    public string $password;

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}