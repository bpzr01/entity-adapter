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

    /**
     * @param class-string<BackedEnum> $typeName
     * @inheritDoc
     */
    public function fromDb(string $typeName, mixed $value, array $subscribedAttributes): BackedEnum
    {
        return $typeName::from($value);
    }

    /**
     * @param BackedEnum $value
     * @inheritDoc
     */
    public function toDb(mixed $value, array $subscribedAttributes): int|string
    {
        return $value->value;
    }

    /** @inheritDoc */
    public function getSubscribedParamAttributeFqn(): null
    {
        return null;
    }
}
