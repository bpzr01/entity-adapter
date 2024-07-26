<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter\Parameter;

use Bpzr\EntityAdapter\ValueConvertor\Abstract\ValueConvertorInterface;

readonly class EntityParamSchema
{
    /** @param array<object> $subscribedParamAttributes */
    public function __construct(
        private string $name,
        private string $typeName,
        private bool $allowsNull,
        private string $columnName,
        private ValueConvertorInterface $valueConvertor,
        private bool $isContingent,
        private array $subscribedParamAttributes,
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
    public function getSubscribedParamAttributes(): array
    {
        return $this->subscribedParamAttributes;
    }
}