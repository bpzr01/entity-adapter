<?php

namespace Bpzr\EntityHydrator\ValueConvertor;

use Bpzr\EntityHydrator\ValueConvertor\Abstract\ValueConvertorInterface;
use DateTimeImmutable;

class DateTimeImmutableValueConvertor implements ValueConvertorInterface
{
    /** @inheritDoc */
    public function shouldApply(string $typeName, string $entityFqn): bool
    {
        return $typeName === DateTimeImmutable::class;
    }

    /** @inheritDoc */
    public function apply(string $typeName, mixed $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value);
    }
}