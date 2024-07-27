<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter;

use Bpzr\EntityAdapter\Cache\EntityParamsDataCache;
use Bpzr\EntityAdapter\Exception\EntityAdapterException;
use Bpzr\EntityAdapter\Utils\StringUtils;
use Bpzr\EntityAdapter\ValueConvertor\Abstract\ValueConvertorFactoryInterface;
use Bpzr\EntityAdapter\ValueConvertor\Abstract\ValueConvertorInterface;
use Doctrine\DBAL\Result;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionParameter;
use Throwable;

class EntityAdapter
{
    /** @var array<ValueConvertorInterface> $valueConvertors */
    private array $valueConvertors;
    private EntityParamsDataCache $paramsDataCache;

    /** @param int|null $useGeneratorRowCountThreshold null - never use generator */
    public function __construct(
        private readonly ValueConvertorFactoryInterface $valueConvertorFactory,
        private readonly ?int $useGeneratorRowCountThreshold = 1500,
    ) {
        $this->valueConvertors = $this->valueConvertorFactory->createAll();
        $this->paramsDataCache = new EntityParamsDataCache();
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

        $entityReflection = $this->prepareEntityReflection($entityFqn);
        $reflectionParams = $entityReflection->getConstructor()->getParameters();

        $entity = $this->createEntity(
            $entityFqn,
            $entityReflection,
            $reflectionParams,
            $this->prepareColumnNames($reflectionParams),
            $rows[0],
        );

        $this->paramsDataCache->clearSubscribedAttributes();

        return $entity;
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

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            return [];
        }

        $rows = $this->useGeneratorRowCountThreshold === null || $this->useGeneratorRowCountThreshold > $rowCount
            ? $query->fetchAllAssociative()
            : $query->iterateAssociative();

        $reflectionParams = $entityReflection->getConstructor()->getParameters();
        $columnNames = $this->prepareColumnNames($reflectionParams);

        $entities = [];

        foreach ($rows as $row) {
            $entity = $this->createEntity($entityFqn, $entityReflection, $reflectionParams, $columnNames, $row);

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

        $this->paramsDataCache->clearSubscribedAttributes();

        return $entities;
    }

    /**
     * @param array<ReflectionParameter> $reflectionParams
     * @return array<string, string> param name => column name
     */
    private function prepareColumnNames(array $reflectionParams): array
    {
        $columnNames = [];

        foreach ($reflectionParams as $param) {
            $paramName = $param->getName();

            $columnNames[$paramName] = StringUtils::camelToSnakeCase($paramName);
        }

        return $columnNames;
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
     * @template T
     * @param class-string<T> $entityFqn
     * @param array<ReflectionParameter> $reflectionParams
     * @param array<string, string > $columnNames
     * @param array<string, mixed> $rowAssoc
     * @return T
     */
    private function createEntity(
        string $entityFqn,
        ReflectionClass $entityReflection,
        array $reflectionParams,
        array $columnNames,
        array $rowAssoc,
    ): object {
        $args = [];

        foreach ($reflectionParams as $param) {
            $paramName = $param->getName();
            $columnName = $columnNames[$paramName];

            if (! array_key_exists($columnName, $rowAssoc)) {
                throw $this->generateEntityAdapterException(
                    $entityFqn,
                    "Could not get value of parameter {$paramName} by database column {$columnName}",
                );
            }

            $rawValue = $rowAssoc[$columnName];

            if ($rawValue === null) {
                $args[$param->getName()] = null;

                continue;
            }

            $paramTypeName = $param->getType()->getName();

            $valueConvertor = $this->paramsDataCache->getValueConvertor(
                $paramTypeName,
                function () use ($paramTypeName, $entityFqn) {
                    foreach ($this->valueConvertors as $convertor) {
                        if ($convertor->shouldApply($paramTypeName, $entityFqn)) {
                            return $convertor;
                        }
                    }

                    throw $this->generateEntityAdapterException($entityFqn, "Type {$paramTypeName} is not supported");
                }
            );

            $subscribedAttributes = $this->paramsDataCache->getSubscribedAttributes(
                $paramName,
                function () use ($valueConvertor, $param) {
                    $subscribedAttributeFqn = $valueConvertor->getSubscribedParamAttributeFqn();

                    return $subscribedAttributeFqn === null
                        ? []
                        : array_map(
                            fn (ReflectionAttribute $attribute): object => $attribute->newInstance(),
                            $param->getAttributes($subscribedAttributeFqn),
                        );
                }
            );

            try {
                $args[$param->getName()] = $valueConvertor->fromDb(
                    $paramTypeName,
                    $rawValue,
                    $subscribedAttributes,
                );
            } catch (Throwable $e) {
                throw $this->generateEntityAdapterException($entityFqn, $e->getMessage(), $e);
            }
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