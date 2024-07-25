<?php

namespace Bpzr\EntityAdapter;

class UserEntity
{
    public function __construct(
        public int $userId,
        public string $username,
        public string $firstName,
        public string $lastName,
        public string $gender,
        public string $password,
        public bool $status,
    ) {
    }

    public function getUserId(): string
    {
        return $this->userId;
    }
}