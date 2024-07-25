<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter;

use Bpzr\EntityAdapter\Attribute\Contingent;
use Bpzr\EntityAdapter\Exception\EntityAdapterException;
use Bpzr\EntityAdapter\Parameter\EntityParam;
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

    /** @var array<string, ValueConvertorInterface> $valueConvertorCache */
    private array $valueConvertorCache = [];

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

        $entityReflection = $this->prepareEntityReflection($entityFqn);

        // TODO: This is not optimal - consider using different strategy for this method

        return $this->createEntity(
            $entityFqn,
            $entityReflection,
            $this->prepareParams($entityFqn, $entityReflection),
            $rows[0],
        );
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

        if (iterator_count($rows) === 0) {
            return [];
        }

        $entityParams = $this->prepareParams($entityFqn, $entityReflection);

        $entities = [];

        foreach ($rows as $row) {
            $entity = $this->createEntity($entityFqn, $entityReflection, $entityParams, $row);

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
     * @template T
     * @param class-string<T> $entityFqn
     * @param array<EntityParam> $entityParams
     * @param array<string, mixed> $rowAssoc
     * @return T
     */
    private function createEntity(
        string $entityFqn,
        ReflectionClass $entityReflection,
        array $entityParams,
        array $rowAssoc,
    ): object {
        $args = [];

        foreach ($entityParams as $param) {
            if (! array_key_exists($param->getColumnName(), $rowAssoc)) {
                throw $this->generateEntityAdapterException(
                    $entityFqn,
                    "Could not get value of property {$param->getName()} by database column {$param->getColumnName()}",
                );
            }

            $rawValue = $rowAssoc[$param->getColumnName()];

            if ($rawValue === null) {
                if (! $param->allowsNull()) {
                    throw $this->generateEntityAdapterException(
                        $entityFqn,
                        "Value of property {$param->getName()} must not be null in the database",
                    );
                }

                if ($param->isContingent()) {
                    throw $this->generateEntityAdapterException(
                        $entityFqn,
                        "Value of contingent property {$param->getName()} must not be null in the database",
                    );
                }

                $args[$param->getName()] = null;

                continue;
            }

            try {
                $args[$param->getName()] = $param->getValueConvertor()->fromDb(
                    $param->getTypeName(),
                    $rawValue,
                    $param->getSubscribedPropAttributes(),
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

    /** @return array<EntityParam> */
    private function prepareParams(string $entityFqn, ReflectionClass $entityReflection): array
    {
        $reflectionParams = $entityReflection->getConstructor()->getParameters();

        $entityParams = [];

        foreach ($reflectionParams as $reflectionParam) {
            $paramName = $reflectionParam->getName();
            $paramType = $reflectionParam->getType();

            if ($paramType === null) {
                throw $this->generateEntityAdapterException($entityFqn, "Property {$paramName} is missing type hint");
            }

            $valueConvertor = $this->selectConvertor($paramType->getName(), $entityFqn);

            $subscribedAttributeFqn = $valueConvertor->getSubscribedAttributeFqn();
            $subscribedPropAttributes = $subscribedAttributeFqn === null
                ? []
                : array_map(
                    fn (ReflectionAttribute $attribute) => $attribute->newInstance(),
                    $reflectionParam->getAttributes($subscribedAttributeFqn),
                );

            $entityParams[] = new EntityParam(
                $paramName,
                $paramType->getName(),
                $paramType->allowsNull(),
                StringUtils::camelToSnakeCase($paramName),
                $valueConvertor,
                count($reflectionParam->getAttributes(Contingent::class)) !== 0,
                $subscribedPropAttributes,
            );
        }

        return $entityParams;
    }

    private function selectConvertor(string $paramTypeName, string $entityFqn): ValueConvertorInterface
    {
        if (array_key_exists($paramTypeName, $this->valueConvertorCache)) {
            return $this->valueConvertorCache[$paramTypeName];
        }

        foreach ($this->valueConvertors as $valueConvertor) {
            if ($valueConvertor->shouldApply($paramTypeName, $entityFqn)) {
                $this->valueConvertorCache[$paramTypeName] = $valueConvertor;

                return $valueConvertor;
            }
        }

        throw $this->generateEntityAdapterException($entityFqn, "Type {$paramTypeName} is not supported");
    }
}
