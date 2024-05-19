<?php

declare(strict_types=1);

namespace Bpzr\Tests;

use Bpzr\EntityHydrator\EntityHydrator;
use Bpzr\EntityHydrator\Exception\EntityHydratorException;
use Bpzr\Tests\Fixture\Entity\MissingTypeHintEntityFixture;
use Bpzr\Tests\Fixture\Entity\NonConstructibleEntityFixture;
use Bpzr\Tests\Fixture\Entity\NonInstantiableEntityFixture;
use Bpzr\Tests\Fixture\Entity\ProductEntityFixture;
use Bpzr\Tests\Fixture\Entity\UnsupportedDataTypeEntityFixture;
use Bpzr\Tests\Fixture\Entity\UserEntityFixture;
use Bpzr\Tests\Fixture\Enum\UserTypeEnum;
use DateTimeImmutable;
use Doctrine\DBAL\Result;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EntityHydratorTest extends TestCase
{
    public static function createOneDataProvider(): Generator
    {
        yield [
            'queryResult' => [
                0 => [
                    'id' => 1,
                    'username' => 'John Doe',
                    'password' => '$gjh@Dadasdcs',
                    'is_subscriber' => 1,
                    'average_order_price' => 1267.209,
                    'registered_at' => '2022-01-01 12:12:12',
                    'user_type' => 'REGULAR',
                    'config' => '{test: true}',
            ]],
            'entityFqn' => UserEntityFixture::class,
            'expected' => new UserEntityFixture(
                1,
                'John Doe',
                '$gjh@Dadasdcs',
                true,
                1267.209,
                new DateTimeImmutable('2022-01-01 12:12:12'),
                UserTypeEnum::REGULAR,
                '{test: true}'
            ),
        ];
        yield [
            'queryResult' => [
                0 => ['id' => 123],
                1 => ['id' => 789],
            ],
            'entityFqn' => UserEntityFixture::class,
            'expectTooManyRowsException' => true,
        ];
        yield [
            'queryResult' => [],
            'entityFqn' => UserEntityFixture::class,
            'expected' => null,
        ];
        yield [
            'queryResult' => [0 => ['test' => 1234]],
            'entityFqn' => NonInstantiableEntityFixture::class,
            'expectEntityIsNotInstantiableException' => true,
        ];
        yield [
            'queryResult' => [0 => []],
            'entityFqn' => NonConstructibleEntityFixture::class,
            'expectEntityIsNotConstructibleException' => true,
        ];
        yield [
            'queryResult' => [0 => []],
            'entityFqn' => UserEntityFixture::class,
            'expectCouldNotGetValueByColumnException' => true,
        ];
        yield [
            'queryResult' => [0 => []],
            'entityFqn' => UserEntityFixture::class,
            'expectCouldNotGetValueByColumnException' => true,
        ];
        yield [
            'queryResult' => [0 => ['id' => 9091]],
            'entityFqn' => MissingTypeHintEntityFixture::class,
            'expectMissingTypeHintException' => true,
        ];
        yield [
            'queryResult' => [0 => ['id' => 1, 'username' => null, 'password' => null]],
            'entityFqn' => UserEntityFixture::class,
            'expectPropertyIsNotNullableException' => true,
        ];
        yield [
            'queryResult' => [0 => ['id' => null]],
            'entityFqn' => UserEntityFixture::class,
            'expectContingentPropertyIsNotNullableException' => true,
        ];
        yield [
            'queryResult' => [0 => ['enum' => 'testCase']],
            'entityFqn' => UnsupportedDataTypeEntityFixture::class,
            'expectUnsupportedDataTypeException' => true,
        ];
    }

    /**
     * @covers \Bpzr\EntityHydrator\EntityHydrator::createOne
     * @covers \Bpzr\EntityHydrator\EntityHydrator::createEntity
     * @covers \Bpzr\EntityHydrator\EntityHydrator::prepareEntityReflection
     */
    #[DataProvider('createOneDataProvider')]
    public function testCreateOne(
        array $queryResult,
        string $entityFqn,
        bool $expectTooManyRowsException = false,
        bool $expectEntityIsNotInstantiableException = false,
        bool $expectEntityIsNotConstructibleException = false,
        bool $expectCouldNotGetValueByColumnException = false,
        bool $expectMissingTypeHintException = false,
        bool $expectPropertyIsNotNullableException = false,
        bool $expectContingentPropertyIsNotNullableException = false,
        bool $expectUnsupportedDataTypeException = false,
        ?UserEntityFixture $expected = null,
    ): void {
        $resultMock = $this->createMock(Result::class);
        $resultMock->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($queryResult);

        if ($expectTooManyRowsException) {
            $this->expectExceptionMessageMatches('/Query must not return more than one row/');
            $this->expectException(EntityHydratorException::class);
        }

        if ($expectEntityIsNotInstantiableException) {
            $this->expectExceptionMessageMatches('/Given class is not instantiable/');
            $this->expectException(EntityHydratorException::class);
        }

        if ($expectEntityIsNotConstructibleException) {
            $this->expectExceptionMessageMatches('/Given class must have a constructor/');
            $this->expectException(EntityHydratorException::class);
        }

        if ($expectCouldNotGetValueByColumnException) {
            $this->expectExceptionMessageMatches(
                '/Could not get value of property .* by database column .*/'
            );
            $this->expectException(EntityHydratorException::class);
        }

        if ($expectMissingTypeHintException) {
            $this->expectExceptionMessageMatches('/Property .* is missing type hint/');
            $this->expectException(EntityHydratorException::class);
        }

        if ($expectPropertyIsNotNullableException) {
            $this->expectExceptionMessageMatches('/Value of property .* must not be null in the database/');
            $this->expectException(EntityHydratorException::class);
        }

        if ($expectContingentPropertyIsNotNullableException) {
            $this->expectExceptionMessageMatches('/Value of contingent property .* must not be null in the database/');
            $this->expectException(EntityHydratorException::class);
        }

        if ($expectUnsupportedDataTypeException) {
            $this->expectExceptionMessageMatches('/Type .* is not supported/');
            $this->expectException(EntityHydratorException::class);
        }

        $entity = (new EntityHydrator())->createOne($entityFqn, $resultMock);

        $this->assertEquals($expected, $entity);
    }

    public static function willThrowOnInvalidResultKeyExtractorDataProvider(): Generator
    {
        yield [
            'entityFqn' => UserEntityFixture::class,
            'resultKeyExtractor' => [ProductEntityFixture::class, 'getConfig'],
            'expectWrongEntityException' => true,
        ];
        yield [
            'entityFqn' => UserEntityFixture::class,
            'resultKeyExtractor' => [UserEntityFixture::class, 'someMethodThatDoesNotExist'],
            'expectNonExistingMethodException' => true,
        ];
    }

    /** @covers \Bpzr\EntityHydrator\EntityHydrator::getValidGetterMethodName */
    #[DataProvider('willThrowOnInvalidResultKeyExtractorDataProvider')]
    public function testWillThrowOnInvalidResultKeyExtractor(
        string $entityFqn,
        ?array $resultKeyExtractor = null,
        bool $expectWrongEntityException = false,
        bool $expectNonExistingMethodException = false,
    ): void {
        $resultMock = $this->createMock(Result::class);

        if ($expectWrongEntityException) {
            $this->expectExceptionMessageMatches(
                '/Key extractor class method reference must be the same class as the one being hydrated/',
            );
            $this->expectException(EntityHydratorException::class);
        }

        if ($expectNonExistingMethodException) {
            $this->expectExceptionMessageMatches(
                '/Key extractor class method reference must refer to an existing method/',
            );
            $this->expectException(EntityHydratorException::class);
        }

        (new EntityHydrator())->createAll($entityFqn, $resultMock, $resultKeyExtractor);
    }

    public static function createAllDataProvider(): Generator
    {
        yield [
            'entityFqn' => UserEntityFixture::class,
            'queryResult' => [],
            'resultKeyExtractor' => [UserEntityFixture::class, 'getUsername'],
            'useGeneratorThreshold' => 20,
            'rowCount' => 20,
            'willUseGenerator' => true,
        ];
        yield [
            'entityFqn' => UserEntityFixture::class,
            'queryResult' => [],
            'resultKeyExtractor' => [UserEntityFixture::class, 'getUsername'],
            'useGeneratorThreshold' => 20,
            'rowCount' => 21,
            'willUseGenerator' => true,
        ];
        yield [
            'entityFqn' => UserEntityFixture::class,
            'queryResult' => [],
            'resultKeyExtractor' => [UserEntityFixture::class, 'getUsername'],
            'useGeneratorThreshold' => 20,
            'rowCount' => 281,
            'willUseGenerator' => true,
        ];
        yield [
            'entityFqn' => UserEntityFixture::class,
            'queryResult' => [],
            'resultKeyExtractor' => [UserEntityFixture::class, 'getUsername'],
            'useGeneratorThreshold' => 20,
            'rowCount' => 19,
            'willUseGenerator' => false,
        ];
        yield [
            'entityFqn' => UserEntityFixture::class,
            'queryResult' => [],
            'resultKeyExtractor' => [UserEntityFixture::class, 'getUsername'],
            'useGeneratorThreshold' => 20,
            'rowCount' => 1,
            'willUseGenerator' => false,
        ];
        yield [
            'entityFqn' => UserEntityFixture::class,
            'queryResult' => [],
            'resultKeyExtractor' => [UserEntityFixture::class, 'getUsername'],
            'useGeneratorThreshold' => 20,
            'rowCount' => 0,
            'willUseGenerator' => false,
        ];
        yield [
            'entityFqn' => UserEntityFixture::class,
            'queryResult' => [],
            'resultKeyExtractor' => [UserEntityFixture::class, 'getUsername'],
            'useGeneratorThreshold' => null,
            'rowCount' => 0,
            'willUseGenerator' => false,
        ];
        yield [
            'entityFqn' => UserEntityFixture::class,
            'queryResult' => [],
            'resultKeyExtractor' => [UserEntityFixture::class, 'getUsername'],
            'useGeneratorThreshold' => null,
            'rowCount' => 100,
            'willUseGenerator' => false,
        ];
        yield [
            'entityFqn' => UserEntityFixture::class,
            'queryResult' => [],
            'resultKeyExtractor' => [UserEntityFixture::class, 'getUsername'],
            'useGeneratorThreshold' => null,
            'rowCount' => 9999,
            'willUseGenerator' => false,
        ];
    }

    /** @covers \Bpzr\EntityHydrator\EntityHydrator::createAll */
    #[DataProvider('createAllDataProvider')]
    public function testCreateAll(
        string $entityFqn,
        array $queryResult,
        ?array $resultKeyExtractor,
        ?int $useGeneratorThreshold,
        int $rowCount,
        bool $willUseGenerator,
    ): void {
        $resultMock = $this->createMock(Result::class);

        if ($useGeneratorThreshold !== null) {
            $resultMock->expects($this->once())->method('rowCount')->willReturn($rowCount);
        }

        $willUseGenerator
            ? $resultMock->expects($this->once())
                ->method('iterateAssociative')
                ->willReturnCallback(function () use ($queryResult) {
                    foreach ($queryResult as $row) {
                        yield $row;
                    }
                })
            : $resultMock->expects($this->once())
                ->method('fetchAllAssociative')
                ->willReturn($queryResult);

        (new EntityHydrator($useGeneratorThreshold))->createAll($entityFqn, $resultMock, $resultKeyExtractor);
    }
}