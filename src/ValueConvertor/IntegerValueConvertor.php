<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter\ValueConvertor;

use Bpzr\EntityAdapter\ValueConvertor\Abstract\ValueConvertorInterface;

class IntegerValueConvertor implements ValueConvertorInterface
{
    /** @inheritDoc */
    public function shouldApply(string $propertyTypeName, string $entityFqn): bool
    {
        return $propertyTypeName === 'int';
    }

    /** @inheritDoc */
    public function fromDb(string $typeName, mixed $value, array $subscribedAttributes): int
    {
        return (int) $value;
    }

    /**
     * @param int $value
     * @inheritDoc
     */
    public function toDb(mixed $value, array $subscribedAttributes): int
    {
        return $value;
    }

    /** @inheritDoc */
    public function getSubscribedAttributeFqn(): null
    {
        return null;
    }
}