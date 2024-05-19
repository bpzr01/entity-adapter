<?php

declare(strict_types=1);

namespace Bpzr\Tests\Fixture\Entity;

class MissingTypeHintEntityFixture
{
    public function __construct(private int $id, private $name)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }
}