<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter;

use Bpzr\EntityAdapter\ValueConvertor\Abstract\ValueConvertorInterface;

readonly class EntityParam
{
    /** @param array<object> $subscribedPropAttributes */
    public function __construct(
        private string $name,
        private string $typeName,
        private bool $allowsNull,
        private string $columnName,
        private ValueConvertorInterface $valueConvertor,
        private bool $isContingent,
        private array $subscribedPropAttributes,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTypeName(): string
    {
        return $this->typeName;
    }

    public function allowsNull(): bool
    {
        return $this->allowsNull;
    }

    public function getColumnName(): string
    {
        return $this->columnName;
    }

    public function getValueConvertor(): ValueConvertorInterface
    {
        return $this->valueConvertor;
    }

    public function isContingent(): bool
    {
        return $this->isContingent;
    }

    /** @return array<object> */
    public function getSubscribedPropAttributes(): array
    {
        return $this->subscribedPropAttributes;
    }
}