<?php

namespace Bpzr\Tests\Fixtures\Entity;

readonly abstract class NonInstantiableEntityFixture
{
    public function __construct(private int $id, private bool $test)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function isTest(): bool
    {
        return $this->test;
    }
}