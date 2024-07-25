<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter\ValueConvertor;

use Bpzr\EntityAdapter\ValueConvertor\Abstract\ValueConvertorInterface;

class FloatValueConvertor implements ValueConvertorInterface
{
    /** @inheritDoc */
    public function shouldApply(string $propertyTypeName, string $entityFqn): bool
    {
        return $propertyTypeName === 'float';
    }

    /** @inheritDoc */
    public function fromDb(string $typeName, mixed $value, array $subscribedAttributes): float
    {
        return (float) $value;
    }

    /**
     * @param float $value
     * @inheritDoc
     */
    public function toDb(mixed $value, array $subscribedAttributes): float
    {
        return $value;
    }

    /** @inheritDoc */
    public function getSubscribedAttributeFqn(): null
    {
        return null;
    }
}