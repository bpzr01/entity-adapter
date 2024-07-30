<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter\ValueConvertor;

use Bpzr\EntityAdapter\Attribute\DateTimeFormat;
use Bpzr\EntityAdapter\ValueConvertor\Abstract\ValueConvertorInterface;
use DateTimeImmutable;
use RuntimeException;

class DateTimeImmutableValueConvertor implements ValueConvertorInterface
{
    /** @inheritDoc */
    public function shouldApply(string $propertyTypeName, string $entityFqn): bool
    {
        return $propertyTypeName === DateTimeImmutable::class;
    }

    /** @inheritDoc */
    public function fromDb(string $typeName, mixed $value, array $subscribedAttributes): DateTimeImmutable
    {
        $formatAttribute = $this->getDateTimeFormatAttribute($subscribedAttributes);

        $dti = DateTimeImmutable::createFromFormat($formatAttribute->getFormat(), $value);

        if ($dti === false) {
            throw new RuntimeException('Failed to create DTI from format: ' . $formatAttribute->getFormat());
        }

        return $dti;
    }

    /**
     * @param DateTimeImmutable $value
     * @inheritDoc
     */
    public function toDb(mixed $value, array $subscribedAttributes): string
    {
        $dateTimeFormatAttribute = $this->getDateTimeFormatAttribute($subscribedAttributes);

        return $value->format($dateTimeFormatAttribute->getFormat());
    }

    /** @inheritDoc */
    public function getSubscribedPropertyAttributeFqn(): ?string
    {
        return DateTimeFormat::class;
    }

    /** @param array<DateTimeFormat> $subscribedAttributes */
    private function getDateTimeFormatAttribute(array $subscribedAttributes): DateTimeFormat
    {
        $dateTimeFormatAttribute = $subscribedAttributes[0] ?? null;

        if ($dateTimeFormatAttribute === null) {
            throw new RuntimeException('Missing ' . DateTimeFormat::class . ' attribute.');
        }

        return $dateTimeFormatAttribute;
    }
}