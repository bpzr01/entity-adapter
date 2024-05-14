<?php

declare(strict_types=1);

namespace Bpzr\EntityHydrator\ValueConvertor;

use Bpzr\EntityHydrator\ValueConvertor\Abstract\ValueConvertorInterface;

class FloatValueConvertor implements ValueConvertorInterface
{
    /** @inheritDoc */
    public function shouldApply(string $typeName, string $entityFqn): bool
    {
        return $typeName === 'float';
    }

    /** @inheritDoc */
    public function apply(string $typeName, mixed $value): float
    {
        return (float) $value;
    }
}