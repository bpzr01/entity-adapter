<?php

declare(strict_types=1);

namespace Bpzr\EntityHydrator\ValueConvertor;

use Bpzr\EntityHydrator\ValueConvertor\Abstract\ValueConvertorInterface;

class BooleanValueConvertor implements ValueConvertorInterface
{
    /** @inheritDoc */
    public function shouldApply(string $typeName, string $entityFqn): bool
    {
        return $typeName === 'bool';
    }

    /** @inheritDoc */
    public function apply(string $typeName, mixed $value): bool
    {
        return (bool) $value;
    }
}