<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter;

use Bpzr\EntityAdapter\Attribute\Contingent;
use Bpzr\EntityAdapter\Exception\EntityAdapterException;
use Bpzr\EntityAdapter\Utils\StringUtils;
use Bpzr\EntityAdapter\ValueConvertor\Abstract\ValueConvertorFactoryInterface;
use Bpzr\EntityAdapter\ValueConvertor\Abstract\ValueConvertorInterface;
use Doctrine\DBAL\Result;
use ReflectionAttribute;
use ReflectionClass;
use Throwable;

class EntityAdapter
{
    /** @var array<ValueConvertorInterface> $valueConvertors */
    private array $valueConvertors;

    /** @var array<string, ValueConvertorInterface> $convertorCache constructor param data type name => convertor */
    private array $valueConvertorCache = [];

    /** @var array<string, string> $columnNameCache constructor param name => DB column name */
    private array $columnNameCache = [];

    /** @param int|null $useGeneratorRowCountThreshold null - never use generator */
    public function __construct(
        private readonly ValueConvertorFactoryInterface $valueConvertorFactory,
        private readonly ?int $useGeneratorRowCountThreshold = 1500,
    ) {
        $this->valueConvertors = $this->valueConvertorFactory->createAll();
    }

    /**
     * @template T
     * @param class-string<T> $entityFqn
     * @return T|null
     */
    public function createOne(string $entityFqn, Result $query): ?object
    {
        $rows = $query->fetchAllAssociative();
        $rowCount = count($rows);

        if ($rowCount > 1) {
            throw $this->generateEntityAdapterException($entityFqn, 'Query must not return more than one row');
        }

        if ($rowCount === 0) {
            return null;
        }

        return $this->createEntity($entityFqn, $this->prepareEntityReflection($entityFqn), $rows[0]);
    }

    /**
     * @template T
     * @param class-string<T> $entityFqn
     * @param array{class-string<T>, string}|null $resultKeyExtractMethod entity class FQN => method name
     * @return array<T>
     */
    public function createAll(string $entityFqn, Result $query, ?array $resultKeyExtractMethod = null): array
    {
        $entityReflection = $this->prepareEntityReflection($entityFqn);

        $keyExtractMethodName = $resultKeyExtractMethod === null
            ? null
            : $this->prepareKeyExtractMethodName($entityReflection, $resultKeyExtractMethod);

        $rows = $this->useGeneratorRowCountThreshold === null
            || $this->useGeneratorRowCountThreshold > $query->rowCount()
                ? $query->fetchAllAssociative()
                : $query->iterateAssociative();

        $entities = [];

        foreach ($rows as $row) {
            $entity = $this->createEntity($entityFqn, $entityReflection, $row);

            if ($keyExtractMethodName === null) {
                $entities[] = $entity;
            } else {
                $key = $entity->$keyExtractMethodName();

                if (array_key_exists($key, $entities)) {
                    throw $this->generateEntityAdapterException($entityFqn, 'Result keys must be unique');
                }

                $entities[$key] = $entity;
            }
        }

        return $entities;
    }

    private function prepareEntityReflection(string $entityFqn): ReflectionClass
    {
        $reflectionClass = new ReflectionClass($entityFqn);

        if (! $reflectionClass->isInstantiable()) {
            throw $this->generateEntityAdapterException($entityFqn, 'Given class is not instantiable');
        }

        if ($reflectionClass->getConstructor() === null) {
            throw $this->generateEntityAdapterException($entityFqn, 'Given class must have a constructor');
        }

        return $reflectionClass;
    }

    /**
     * @param array<string, mixed> $rowAssoc
     *
     * @template T
     * @param class-string<T> $entityFqn
     * @return T
     */
    private function createEntity(string $entityFqn, ReflectionClass $entityReflection, array $rowAssoc): object
    {
        $constructorParams = $entityReflection->getConstructor()->getParameters();

        $args = [];

        foreach ($constructorParams as $constructorParam) {
            $constructorParamType = $constructorParam->getType();
            $constructorParamName = $constructorParam->getName();

            if ($constructorParamType === null) {
                throw $this->generateEntityAdapterException(
                    $entityFqn,
                    "Property {$constructorParamName} is missing type hint",
                );
            }

            $columnName = $this->columnNameCache[$constructorParamName]
                ??= StringUtils::camelToSnakeCase($constructorParamName);

            if (! array_key_exists($columnName, $rowAssoc)) {
                throw $this->generateEntityAdapterException(
                    $entityFqn,
                    "Could not get value of property {$constructorParamName} by database column {$columnName}",
                );
            }

            $rawValue = $rowAssoc[$columnName];

            if ($rawValue === null) {
                if (! $constructorParamType->allowsNull()) {
                    throw $this->generateEntityAdapterException(
                        $entityFqn,
                        "Value of property {$constructorParamName} is not expected to be nullable",
                    );
                }

                if (count($constructorParam->getAttributes(Contingent::class)) !== 0) {
                    throw $this->generateEntityAdapterException(
                        $entityFqn,
                        "Value of contingent property {$constructorParamName} must not be null in the database",
                    );
                }

                $args[$constructorParamName] = null;

                continue;
            }

            $constructorParamTypeName = $constructorParamType->getName();

            // TODO: Refactor and cache
            if (array_key_exists($constructorParamTypeName, $this->valueConvertorCache)) {
                $valueConvertor = $this->valueConvertorCache[$constructorParamTypeName];

                $subscribedAttributeFqn = $valueConvertor->getSubscribedAttributeFqn();

                $subscribedPropAttributes = $subscribedAttributeFqn === null
                    ? []
                    : array_map(
                        fn (ReflectionAttribute $attribute) => $attribute->newInstance(),
                        $constructorParam->getAttributes($subscribedAttributeFqn),
                    );

                try {
                    $args[$constructorParamName] = $valueConvertor->fromDb(
                        $constructorParamTypeName,
                        $rawValue,
                        $subscribedPropAttributes,
                    );
                } catch (Throwable $e) {
                    throw $this->generateEntityAdapterException($entityFqn, $e->getMessage(), $e);
                }

                continue;
            } else {
                foreach ($this->valueConvertors as $valueConvertor) {
                    $shouldApply = $valueConvertor->shouldApply($constructorParamTypeName, $entityFqn);

                    if ($shouldApply) {
                        $this->valueConvertorCache[$constructorParamTypeName] = $valueConvertor;

                        $subscribedAttributeFqn = $valueConvertor->getSubscribedAttributeFqn();

                        $subscribedPropAttributes = $subscribedAttributeFqn === null
                            ? []
                            : array_map(
                                fn (ReflectionAttribute $attribute) => $attribute->newInstance(),
                                $constructorParam->getAttributes($subscribedAttributeFqn),
                            );

                        try {
                            $args[$constructorParamName] = $valueConvertor->fromDb(
                                $constructorParamTypeName,
                                $rawValue,
                                $subscribedPropAttributes,
                            );
                        } catch (Throwable $e) {
                            throw $this->generateEntityAdapterException($entityFqn, $e->getMessage(), $e);
                        }

                        continue 2;
                    }
                }
            }

            throw $this->generateEntityAdapterException(
                $entityFqn,
                "Type {$constructorParamTypeName} is not supported",
            );
        }

        return $entityReflection->newInstanceArgs($args);
    }

    /**
     * @template T
     * @param ReflectionClass<T> $entityReflection
     * @param array{class-string<T>, string} $resultKeyExtractMethod
     * @return string entity method name
     */
    private function prepareKeyExtractMethodName(
        ReflectionClass $entityReflection,
        array $resultKeyExtractMethod,
    ): string {
        $entityFqn = $entityReflection->getName();

        if (($resultKeyExtractMethod[0] ?? null) !== $entityFqn) {
            throw $this->generateEntityAdapterException(
                $entityFqn,
                'Key extractor class method reference must be the same class as the one being hydrated',
            );
        }

        $getterMethodName = $resultKeyExtractMethod[1] ?? null;

        if ($getterMethodName === null || ! $entityReflection->hasMethod($getterMethodName)) {
            throw $this->generateEntityAdapterException(
                $entityFqn,
                'Key extractor class method reference must refer to an existing method',
            );
        }

        return $getterMethodName;
    }

    private function generateEntityAdapterException(
        string $entityFqn,
        ?string $additionalMessage = null,
        ?Throwable $previous = null,
    ): EntityAdapterException {
        return new EntityAdapterException(
            "Creating entity from class {$entityFqn} failed." . (
                $additionalMessage === null
                    ? ''
                    : " Error: {$additionalMessage}"
            ),
            $previous,
        );
    }
}
