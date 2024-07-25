<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter\ValueConvertor\Abstract;

interface ValueConvertorInterface
{
    /**
     * @param string|class-string $propertyTypeName
     * @param class-string $entityFqn
     */
    public function shouldApply(string $propertyTypeName, string $entityFqn): bool;

    /**
     * @param string|class-string $typeName
     * @param array<object> $subscribedAttributes object of {@see self::getSubscribedAttributeFqn()}
     */
    public function fromDb(string $typeName, mixed $value, array $subscribedAttributes): mixed;

    /** @param array<object> $subscribedAttributes object of {@see self::getSubscribedAttributeFqn()} */
    public function toDb(mixed $value, array $subscribedAttributes): int|string|float;

    /** @return class-string|null */
    public function getSubscribedAttributeFqn(): ?string;
}