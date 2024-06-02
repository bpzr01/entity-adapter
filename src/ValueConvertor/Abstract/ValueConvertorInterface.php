<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter\ValueConvertor\Abstract;

interface ValueConvertorInterface
{
    /**
     * @param string|class-string $propertyTypeName
     * @param class-string $entityFqn
     */
    public function shouldApply(string $propertyTypeName, string $entityFqn): bool;

    /** @param string|class-string $typeName */
    public function apply(string $typeName, mixed $value): mixed;
}