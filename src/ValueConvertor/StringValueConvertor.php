<?php

declare(strict_types=1);

namespace Bpzr\EntityHydrator\ValueConvertor;

use Bpzr\EntityHydrator\ValueConvertor\Abstract\ValueConvertorInterface;

class StringValueConvertor implements ValueConvertorInterface
{
    /** @inheritDoc */
    public function shouldApply(string $typeName, string $entityFqn): bool
    {
        return $typeName === 'string';
    }

    /** @inheritDoc */
    public function apply(string $typeName, mixed $value): string
    {
        return (string) $value;
    }
}