<?php

declare(strict_types=1);

namespace Bpzr\EntityHydrator;

use Bpzr\EntityHydrator\Attribute\Contingent;
use Bpzr\EntityHydrator\Exception\EntityHydratorException;
use Bpzr\EntityHydrator\Utils\StringUtils;
use Bpzr\EntityHydrator\ValueConvertor\Abstract\ValueConvertorInterface;
use Bpzr\EntityHydrator\ValueConvertor\BackedEnumValueConvertor;
use Bpzr\EntityHydrator\ValueConvertor\BooleanValueConvertor;
use Bpzr\EntityHydrator\ValueConvertor\DateTimeImmutableValueConvertor;
use Bpzr\EntityHydrator\ValueConvertor\FloatValueConvertor;
use Bpzr\EntityHydrator\ValueConvertor\IntegerValueConvertor;
use Bpzr\EntityHydrator\ValueConvertor\StringValueConvertor;
use Doctrine\DBAL\Result;
use ReflectionClass;

readonly class EntityHydrator
{
    /** @var array<ValueConvertorInterface> $valueConvertors */
    private array $valueConvertors;

    /**
     * @param int|null $useGeneratorRowCountThreshold null - never use generator
     * @param array<ValueConvertorInterface> $additionalValueConvertors
     */
    public function __construct(
        private ?int $useGeneratorRowCountThreshold = 150,
        array $additionalValueConvertors = [],
    ) {
        $this->valueConvertors = array_merge($additionalValueConvertors, [
            new StringValueConvertor(),
            new IntegerValueConvertor(),
            new FloatValueConvertor(),
            new BooleanValueConvertor(),
            new DateTimeImmutableValueConvertor(),
            new BackedEnumValueConvertor(),
        ]);
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
            throw $this->generateEntityHydratorException($entityFqn, 'Query must not return more than one row');
        }

        if ($rowCount === 0) {
            return null;
        }

        return $this->createEntity($entityFqn, $this->prepareEntityReflection($entityFqn), $rows[0]);
    }

    /**
     * @template T
     * @param class-string<T> $entityFqn
     * @param array{class-string<T>, string}|null $resultKeyExtractMethod hydrated class FQN => method name
     * @return array<T>
     */
    public function createAll(string $entityFqn, Result $query, ?array $resultKeyExtractMethod = null): array
    {
        $entityReflection = $this->prepareEntityReflection($entityFqn);

        $keyExtractMethodName = $resultKeyExtractMethod === null
            ? null
            : $this->getValidKeyExtractMethodName($entityReflection, $resultKeyExtractMethod);

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
                    throw $this->generateEntityHydratorException($entityFqn, 'Result keys must be unique');
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
            throw $this->generateEntityHydratorException($entityFqn, 'Given class is not instantiable');
        }

        if ($reflectionClass->getConstructor() === null) {
            throw $this->generateEntityHydratorException($entityFqn, 'Given class must have a constructor');
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

        /** @var array<string, ValueConvertorInterface> $convertorCache type name => convertor */
        static $convertorCache = [];

        $args = [];

        foreach ($constructorParams as $constructorParam) {
            $constructorParamType = $constructorParam->getType();
            $constructorParamName = $constructorParam->getName();

            if ($constructorParamType === null) {
                throw $this->generateEntityHydratorException(
                    $entityFqn,
                    "Property {$constructorParamName} is missing type hint",
                );
            }

            $columnName = StringUtils::camelToSnakeCase($constructorParamName);

            if (! array_key_exists($columnName, $rowAssoc)) {
                throw $this->generateEntityHydratorException(
                    $entityFqn,
                    "Could not get value of property {$constructorParamName} by database column {$columnName}",
                );
            }

            $rawValue = $rowAssoc[$columnName];

            $constructorParamTypeName = $constructorParamType->getName();

            if ($rawValue === null) {
                if (! $constructorParamType->allowsNull()) {
                    throw $this->generateEntityHydratorException(
                        $entityFqn,
                        "Value of property {$constructorParamName} must not be null in the database",
                    );
                }

                if (count($constructorParam->getAttributes(Contingent::class)) !== 0) {
                    throw $this->generateEntityHydratorException(
                        $entityFqn,
                        "Value of contingent property {$constructorParamName} must not be null in the database",
                    );
                }

                $args[$constructorParamName] = null;

                continue;
            }

            if (array_key_exists($constructorParamTypeName, $convertorCache)) {
                $args[$constructorParamName] = $convertorCache[$constructorParamTypeName]
                    ->apply($constructorParamTypeName, $rawValue);

                continue;
            } else {
                foreach ($this->valueConvertors as $valueConvertor) {
                    if ($valueConvertor->shouldApply($constructorParamTypeName, $entityFqn)) {
                        $convertorCache[$constructorParamTypeName] = $valueConvertor;

                        $args[$constructorParamName] = $valueConvertor->apply($constructorParamTypeName, $rawValue);

                        continue 2;
                    }
                }
            }

            throw $this->generateEntityHydratorException(
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
    private function getValidKeyExtractMethodName(
        ReflectionClass $entityReflection,
        array $resultKeyExtractMethod,
    ): string {
        $entityFqn = $entityReflection->getName();

        if (($resultKeyExtractMethod[0] ?? null) !== $entityFqn) {
            throw $this->generateEntityHydratorException(
                $entityFqn,
                'Key extractor class method reference must be the same class as the one being hydrated',
            );
        }

        $getterMethodName = $resultKeyExtractMethod[1] ?? null;

        if ($getterMethodName === null || ! $entityReflection->hasMethod($getterMethodName)) {
            throw $this->generateEntityHydratorException(
                $entityFqn,
                'Key extractor class method reference must refer to an existing method',
            );
        }

        return $getterMethodName;
    }

    private function generateEntityHydratorException(string $entityFqn, string $additionalMessage): EntityHydratorException
    {
        return new EntityHydratorException(
            "Creating entity from class {$entityFqn} failed. Error: {$additionalMessage}",
        );
    }
}
