<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter\ValueConvertor\Abstract;

interface ValueConvertorFactoryInterface
{
    /** @return array<ValueConvertorInterface> */
    public function createAll(): array;
}