<?php

declare(strict_types=1);

namespace Bpzr\EntityHydrator;

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
     * @return array<T>
     */
    public function createAll(string $entityFqn, Result $query, ?array $indexByGetter = null): array
    {
        $keyPropertyGetter = $indexByGetter === null
            ? null
            : $this->getValidGetterMethodName($entityFqn, $indexByGetter);

        $entityReflection = $this->prepareEntityReflection($entityFqn);

        $rows = $this->useGeneratorRowCountThreshold === null
            || $this->useGeneratorRowCountThreshold > $query->rowCount()
                ? $query->fetchAllAssociative()
                : $query->iterateAssociative();

        $entities = [];

        foreach ($rows as $row) {
            $entity = $this->createEntity($entityFqn, $entityReflection, $row);

            $keyPropertyGetter === null
                ? $entities[] = $entity
                : $entities[$entity->$keyPropertyGetter()] = $entity;
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
            if ($constructorParam->getType() === null) {
                throw $this->generateEntityHydratorException($entityFqn, 'Property is missing type hint');
            }

            $paramName = StringUtils::camelToSnakeCase($constructorParam->getName());

            $value = $rowAssoc[$paramName] ?? throw $this->generateEntityHydratorException(
                $entityFqn,
                'Could not get value by database column name ' . $paramName,
            );

            $constructorParamTypeName = $constructorParam->getType()->getName();

            if ($value === null) {
                if (! $constructorParam->getType()->allowsNull()) {
                    throw $this->generateEntityHydratorException(
                        $entityFqn,
                        "Value of property {$constructorParam->getName()} must not be null in the database",
                    );
                }

                $args[$constructorParam->getName()] = null;

                continue;
            }

            if (array_key_exists($constructorParamTypeName, $convertorCache)) {
                $args[$constructorParam->getName()] = $convertorCache[$constructorParamTypeName]
                    ->apply($constructorParamTypeName, $value);

                continue;
            } else {
                foreach ($this->valueConvertors as $valueConvertor) {
                    if ($valueConvertor->shouldApply($constructorParamTypeName, $entityFqn)) {
                        $convertorCache[$constructorParamTypeName] = $valueConvertor;

                        $args[$constructorParam->getName()] = $valueConvertor->apply($constructorParamTypeName, $value);

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
     * @param array{string, string} $indexByGetter
     * @return string key getter method name
     */
    private function getValidGetterMethodName(string $entityFqn, array $indexByGetter): string
    {
        if (! is_callable($indexByGetter, syntax_only: true)) {
            throw $this->generateEntityHydratorException(
                $entityFqn,
                'indexBy must be valid getter method callback',
            );
        }

        if (($indexByGetter[0] ?? null) !== $entityFqn) {
            throw $this->generateEntityHydratorException(
                $entityFqn,
                'indexBy callback class must be the same as than one that is being hydrated',
            );
        }

        $getterMethodName = $indexByGetter[1] ?? null;

        if ($getterMethodName === null || ! method_exists($entityFqn, $getterMethodName)) {
            throw $this->generateEntityHydratorException(
                $entityFqn,
                'indexBy callback method must be an existing getter',
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
