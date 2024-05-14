<?php

declare(strict_types=1);

namespace Bpzr\EntityHydrator\ValueConvertor;

use BackedEnum;
use Bpzr\EntityHydrator\ValueConvertor\Abstract\ValueConvertorInterface;

class BackedEnumValueConvertor implements ValueConvertorInterface
{
    /** @inheritDoc */
    public function shouldApply(string $typeName, string $entityFqn): bool
    {
        return is_subclass_of($typeName, BackedEnum::class);
    }

    /** @inheritDoc */
    public function apply(string $typeName, mixed $value): BackedEnum
    {
        return $typeName::from($value);
    }
}
