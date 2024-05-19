<?php

declare(strict_types=1);

namespace Bpzr\EntityHydrator\Entity;

readonly class User
{
    public function __construct(private int $id, private string $username, private string $email)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}