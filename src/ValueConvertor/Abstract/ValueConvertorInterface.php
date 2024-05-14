<?php

declare(strict_types=1);

namespace Bpzr\EntityHydrator\ValueConvertor\Abstract;

interface ValueConvertorInterface
{
    /**
     * @param string|class-string $typeName
     * @param class-string $entityFqn
     */
    public function shouldApply(string $typeName, string $entityFqn): bool;

    /** @param string|class-string $typeName */
    public function apply(string $typeName, mixed $value): mixed;
}