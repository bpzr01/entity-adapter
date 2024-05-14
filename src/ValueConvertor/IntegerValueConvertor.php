<?php

declare(strict_types=1);

namespace Bpzr\EntityHydrator\ValueConvertor;

use Bpzr\EntityHydrator\ValueConvertor\Abstract\ValueConvertorInterface;

class IntegerValueConvertor implements ValueConvertorInterface
{
    /** @inheritDoc */
    public function shouldApply(string $typeName, string $entityFqn): bool
    {
        return $typeName === 'int';
    }

    /** @inheritDoc */
    public function apply(string $typeName, mixed $value): int
    {
        return (int) $value;
    }
}