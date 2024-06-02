<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter\ValueConvertor;

use Bpzr\EntityAdapter\ValueConvertor\Abstract\ValueConvertorInterface;
use DateTimeImmutable;

class DateTimeImmutableValueConvertor implements ValueConvertorInterface
{
    /** @inheritDoc */
    public function shouldApply(string $propertyTypeName, string $entityFqn): bool
    {
        return $propertyTypeName === DateTimeImmutable::class;
    }

    /** @inheritDoc */
    public function apply(string $typeName, mixed $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value);
    }
}