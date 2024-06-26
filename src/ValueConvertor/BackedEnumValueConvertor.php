<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter\ValueConvertor;

use BackedEnum;
use Bpzr\EntityAdapter\ValueConvertor\Abstract\ValueConvertorInterface;

class BackedEnumValueConvertor implements ValueConvertorInterface
{
    /** @inheritDoc */
    public function shouldApply(string $propertyTypeName, string $entityFqn): bool
    {
        return is_subclass_of($propertyTypeName, BackedEnum::class);
    }

    /** @inheritDoc */
    public function apply(string $typeName, mixed $value): BackedEnum
    {
        return $typeName::from($value);
    }
}
