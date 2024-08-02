<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter;

use Bpzr\EntityAdapter\Attribute\Table;
use Bpzr\EntityAdapter\Exception\EntityAdapterException;
use Bpzr\EntityAdapter\Utils\StringUtils;
use Bpzr\EntityAdapter\ValueConvertor\Abstract\ValueConvertorFactoryInterface;
use Bpzr\EntityAdapter\ValueConvertor\Abstract\ValueConvertorInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

class EntityAdapter
{
    /** @var array<ValueConvertorInterface> $valueConvertors */
    private array $valueConvertors;

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

        $row = $rows[0];

        $entityReflection = new ReflectionClass($entityFqn);
        $reflectionProperties = $entityReflection->getProperties();

        $args = $valueConvertorCache = [];

        foreach ($reflectionProperties as $reflectionProperty) {
            $propName = $reflectionProperty->getName();
            $columnName = StringUtils::camelToSnakeCase($propName);
            $rawValue = $this->getRawPropValueByColumnName($columnName, $row, $entityFqn, $propName);

            if ($rawValue === null) {
                $args[$propName] = null;

                continue;
            }

            // TODO: Include this rule in validation script
            $propTypeName = $reflectionProperty->getType()->getName();

            $valueConvertor = $valueConvertorCache[$propTypeName] ??= $this->selectValueConvertor(
                $propTypeName,
                $entityFqn,
            );

            $subscribedAttributes = $this->createSubscribedAttributes($valueConvertor, $reflectionProperty);

            try {
                $args[$propName] = $valueConvertor->fromDb($propTypeName, $rawValue, $subscribedAttributes);
            } catch (Throwable $e) {
                throw $this->generateEntityAdapterException($entityFqn, $e->getMessage(), $e);
            }
        }

        return $entityReflection->newInstanceArgs($args);
    }

    /**
     * @template T
     * @param class-string<T> $entityFqn
     * @param array{class-string<T>, string}|null $resultKeyExtractMethod entity class FQN => method name
     * @return array<T>
     */
    public function createAll(string $entityFqn, Result $query, ?array $resultKeyExtractMethod = null): array
    {
        $entityReflection = new ReflectionClass($entityFqn);

        $keyExtractMethodName = $resultKeyExtractMethod === null
            ? null
            : $this->prepareKeyExtractMethodName($entityReflection, $resultKeyExtractMethod);

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            return [];
        }

        $rows = $this->useGeneratorRowCountThreshold === null || $this->useGeneratorRowCountThreshold > $rowCount
            ? $query->fetchAllAssociative()
            : $query->iterateAssociative();

        $reflectionProperties = $entityReflection->getProperties();

        $entities = $columnNameCache = $valueConvertorCache = $subscribedAttributeCache = [];

        foreach ($rows as $row) {
            $args = [];

            foreach ($reflectionProperties as $reflectionProperty) {
                $propName = $reflectionProperty->getName();
                $columnName = $columnNameCache[$propName] ??= StringUtils::camelToSnakeCase($propName);
                $rawValue = $this->getRawPropValueByColumnName($columnName, $row, $entityFqn, $propName);

                if ($rawValue === null) {
                    $args[$propName] = null;

                    continue;
                }

                $propTypeName = $reflectionProperty->getType()->getName();

                $valueConvertor = $valueConvertorCache[$propTypeName] ??= $this->selectValueConvertor(
                    $propTypeName,
                    $entityFqn,
                );

                $subscribedAttributes = $subscribedAttributeCache[$propName] ??= $this->createSubscribedAttributes(
                    $valueConvertor,
                    $reflectionProperty,
                );

                try {
                    $args[$propName] = $valueConvertor->fromDb($propTypeName, $rawValue, $subscribedAttributes);
                } catch (Throwable $e) {
                    throw $this->generateEntityAdapterException($entityFqn, $e->getMessage(), $e);
                }
            }

            $entity = $entityReflection->newInstanceArgs($args);

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

    public function insertOne(object $entity, Connection $connection): void
    {
        $entityFqn = $entity::class;
        $entityReflection = new ReflectionClass($entityFqn);
        $reflectionProperties = $entityReflection->getProperties();

        $tableAttribute = $this->createTableAttribute($entityReflection, $entityFqn);

        $insertData = $valueConvertorCache = [];

        foreach ($reflectionProperties as $reflectionProperty) {
            $propName = $reflectionProperty->getName();
            $columnName = StringUtils::camelToSnakeCase($propName);
            $propValue = $reflectionProperty->getValue($entity);

            if ($propValue === null) {
                $insertData[$columnName] = null;

                continue;
            }

            $propTypeName = $reflectionProperty->getType()->getName();

            $valueConvertor = $valueConvertorCache[$propTypeName] ??= $this->selectValueConvertor(
                $propTypeName,
                $entityFqn,
            );

            $subscribedAttributes = $this->createSubscribedAttributes($valueConvertor, $reflectionProperty);

            try {
                $insertData[$columnName] = $valueConvertor->toDb($propValue, $subscribedAttributes);
            } catch (Throwable $e) {
                throw $this->generateEntityAdapterException($entityFqn, $e->getMessage(), $e);
            }
        }

        $connection->insert($tableAttribute->getName(), $insertData);
    }

    private function getRawPropValueByColumnName(
        string $columnName,
        array $row,
        string $entityFqn,
        string $propertyName,
    ): mixed {
        if (! array_key_exists($columnName, $row)) {
            throw $this->generateEntityAdapterException(
                $entityFqn,
                "Could not get value of property {$propertyName} by database column {$columnName}",
            );
        }

        return $row[$columnName];
    }

    /** @return array<object> attribute */
    private function createSubscribedAttributes(
        ValueConvertorInterface $valueConvertor,
        ReflectionProperty $reflectionProperty,
    ): array {
        $subscribedPropAttributeFqn = $valueConvertor->getSubscribedPropertyAttributeFqn();

        return $subscribedPropAttributeFqn === null
            ? []
            : array_map(
                fn (ReflectionAttribute $ra): object => $ra->newInstance(),
                $reflectionProperty->getAttributes()
            );
    }

    private function createTableAttribute(ReflectionClass $entityReflection, string $entityFqn): Table
    {
        $tableAttributes = $entityReflection->getAttributes(Table::class);

        if (count($tableAttributes) === 0) {
            throw $this->generateEntityAdapterException(
                $entityFqn,
                'Entity class must have ' . Table::class . ' attribute',
            );
        }

        return $tableAttributes[0]->newInstance();
    }

    private function selectValueConvertor(string $propTypeName, string $entityFqn): ValueConvertorInterface
    {
        foreach ($this->valueConvertors as $convertor) {
            if ($convertor->shouldApply($propTypeName, $entityFqn)) {
                return $convertor;
            }
        }

        throw $this->generateEntityAdapterException($entityFqn, "Type {$propTypeName} is not supported");
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