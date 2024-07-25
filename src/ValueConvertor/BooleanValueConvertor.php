<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter\ValueConvertor;

use Bpzr\EntityAdapter\ValueConvertor\Abstract\ValueConvertorInterface;

class BooleanValueConvertor implements ValueConvertorInterface
{
    /** @inheritDoc */
    public function shouldApply(string $propertyTypeName, string $entityFqn): bool
    {
        return $propertyTypeName === 'bool';
    }

    /** @inheritDoc */
    public function fromDb(string $typeName, mixed $value, array $subscribedAttributes): bool
    {
        return (bool) $value;
    }

    /**
     * @param bool $value
     * @return int<0,1>
     * @inheritDoc
     */
    public function toDb(mixed $value, array $subscribedAttributes): int
    {
        return (int) $value;
    }

    /** @inheritDoc */
    public function getSubscribedAttributeFqn(): null
    {
        return null;
    }
}