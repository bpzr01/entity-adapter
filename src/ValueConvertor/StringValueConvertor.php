<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter\ValueConvertor;

use Bpzr\EntityAdapter\ValueConvertor\Abstract\ValueConvertorInterface;

class StringValueConvertor implements ValueConvertorInterface
{
    /** @inheritDoc */
    public function shouldApply(string $propertyTypeName, string $entityFqn): bool
    {
        return $propertyTypeName === 'string';
    }

    /** @inheritDoc */
    public function fromDb(string $typeName, mixed $value, array $subscribedAttributes): string
    {
        return (string) $value;
    }

    /**
     * @param string $value
     * @inheritDoc
     */
    public function toDb(mixed $value, array $subscribedAttributes): string
    {
        return $value;
    }

    /** @inheritDoc */
    public function getSubscribedParamAttributeFqn(): null
    {
        return null;
    }
}