<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter\Cache;

use Bpzr\EntityAdapter\ValueConvertor\Abstract\ValueConvertorInterface;

class EntityParamsDataCache
{
    /** @var array<string, ValueConvertorInterface> $valueConvertorCache type name => convertor */
    private array $valueConvertorCache = [];

    /** @var array<string, array<object>> $subscribedAttributesCache param name => subscribed attributes */
    private array $subscribedAttributesCache = [];

    /** @param callable(): ValueConvertorInterface $provideValueConvertor */
    public function getValueConvertor(string $typeName, callable $provideValueConvertor): ValueConvertorInterface
    {
        if (array_key_exists($typeName, $this->valueConvertorCache)) {
            return $this->valueConvertorCache[$typeName];
        }

        $valueConvertor = $provideValueConvertor();

        $this->valueConvertorCache[$typeName] = $valueConvertor;

        return $valueConvertor;
    }

    /**
     * @param callable(): array<object> $provideSubscribedAttributes
     * @return array<object>
     */
    public function getSubscribedAttributes(string $paramName, callable $provideSubscribedAttributes): array
    {
        if (array_key_exists($paramName, $this->subscribedAttributesCache)) {
            return $this->subscribedAttributesCache[$paramName];
        }

        $subscribedAttributes = $provideSubscribedAttributes();

        $this->subscribedAttributesCache[$paramName] = $subscribedAttributes;

        return $subscribedAttributes;
    }

    public function clearSubscribedAttributes(): void
    {
        $this->subscribedAttributesCache = [];
    }
}