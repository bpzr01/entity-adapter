<?php

declare(strict_types=1);

namespace Bpzr\Tests;

use Bpzr\EntityAdapter\EntityAdapter;
use Bpzr\EntityAdapter\Exception\EntityAdapterException;
use Bpzr\EntityAdapter\ValueConvertor\BackedEnumValueConvertor;
use Bpzr\EntityAdapter\ValueConvertor\BooleanValueConvertor;
use Bpzr\EntityAdapter\ValueConvertor\DateTimeImmutableValueConvertor;
use Bpzr\EntityAdapter\ValueConvertor\FloatValueConvertor;
use Bpzr\EntityAdapter\ValueConvertor\IntegerValueConvertor;
use Bpzr\EntityAdapter\ValueConvertor\StringValueConvertor;
use Bpzr\Tests\Fixture\Entity\MissingTypeHintEntityFixture;
use Bpzr\Tests\Fixture\Entity\NonConstructibleEntityFixture;
use Bpzr\Tests\Fixture\Entity\NonInstantiableEntityFixture;
use Bpzr\Tests\Fixture\Entity\NumericPropertyNameEntityFixture;
use Bpzr\Tests\Fixture\Entity\ProductEntityFixture;
use Bpzr\Tests\Fixture\Entity\UnsupportedDataTypeEntityFixture;
use Bpzr\Tests\Fixture\Entity\UserEntityFixture;
use Bpzr\Tests\Fixture\Enum\UserTypeEnum;
use DateTimeImmutable;
use Doctrine\DBAL\Result;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EntityAdapterTest extends TestCase
{
    public static function createOneThrowsDataProvider(): Generator
    {
        yield [
            'queryResult' => [
                0 => ['id' => 123],
                1 => ['id' => 789],
            ],
            'entityFqn' => UserEntityFixture::class,
            'expectTooManyRowsException' => true,
        ];
        yield [
            'queryResult' => [
                ['id' => 123],
                ['id' => 1296],
                ['id' => 31235],
                ['id' => 123163],
                ['id' => 7897869],
                ['id' => 99679017],
            ],
            'entityFqn' => UserEntityFixture::class,
            'expectTooManyRowsException' => true,
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
            'queryResult' => [0 => ['invalid_column_name' => 'column value']],
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
     * @covers \Bpzr\EntityAdapter\EntityAdapter::createOne
     * @covers \Bpzr\EntityAdapter\EntityAdapter::createEntity
     * @covers \Bpzr\EntityAdapter\EntityAdapter::prepareEntityReflection
     * @covers \Bpzr\EntityAdapter\EntityAdapter::generateEntityAdapterException
     */
    #[DataProvider('createOneThrowsDataProvider')]
    public function testCreateOneThrows(
        array $queryResult,
        string $entityFqn,
        bool $expectTooManyRowsException = false,
        bool $expectEntityIsNotInstantiableException = false,
        bool $expectEntityIsNotConstructibleException = false,
        bool $expectCouldNotGetValueByColumnException = false,
        bool $expectMissingTypeHintException = false,
        bool $expectPropertyIsNotNullableException = false,
        bool $expectUnsupportedDataTypeException = false,
        bool $expectContingentPropertyIsNotNullableException = false,
    ): void {
        $resultMock = $this->createMock(Result::class);
        $resultMock->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($queryResult);

        if ($expectTooManyRowsException) {
            $this->expectExceptionMessageMatches('/Query must not return more than one row/');
            $this->expectException(EntityAdapterException::class);
        }

        if ($expectEntityIsNotInstantiableException) {
            $this->expectExceptionMessageMatches('/Given class is not instantiable/');
            $this->expectException(EntityAdapterException::class);
        }

        if ($expectEntityIsNotConstructibleException) {
            $this->expectExceptionMessageMatches('/Given class must have a constructor/');
            $this->expectException(EntityAdapterException::class);
        }

        if ($expectCouldNotGetValueByColumnException) {
            $this->expectExceptionMessageMatches(
                '/Could not get value of property .* by database column .*/'
            );
            $this->expectException(EntityAdapterException::class);
        }

        if ($expectMissingTypeHintException) {
            $this->expectExceptionMessageMatches('/Property .* is missing type hint/');
            $this->expectException(EntityAdapterException::class);
        }

        if ($expectPropertyIsNotNullableException) {
            $this->expectExceptionMessageMatches('/Value of property .* is not expected to be nullable/');
            $this->expectException(EntityAdapterException::class);
        }

        if ($expectContingentPropertyIsNotNullableException) {
            $this->expectExceptionMessageMatches('/Value of contingent property .* must not be null in the database/');
            $this->expectException(EntityAdapterException::class);
        }

        if ($expectUnsupportedDataTypeException) {
            $this->expectExceptionMessageMatches('/Type .* is not supported/');
            $this->expectException(EntityAdapterException::class);
        }

        (new EntityAdapter())->createOne($entityFqn, $resultMock);
    }

    public static function createOneDataProvider(): Generator
    {
        yield [
            'queryResult' => [],
            'entityFqn' => UserEntityFixture::class,
            'expected' => null,
        ];
        yield [
            'queryResult' => [0 => [
                'id' => 1,
                'username' => 'test username',
                'password' => 'test password',
                'is_subscriber' => true,
                'average_order_price' => 1678.01,
                'registered_at' => '2000-01-28 18:10:58',
                'user_type' => 'PREMIUM',
                'config' => '{is_test: true}',
            ]],
            'entityFqn' => UserEntityFixture::class,
            'expected' => new UserEntityFixture(
                1,
                'test username',
                'test password',
                true,
                1678.01,
                new DateTimeImmutable('2000-01-28 18:10:58'),
                UserTypeEnum::PREMIUM,
                '{is_test: true}',
            ),
        ];
        yield [
            'queryResult' => [0 => [
                'id' => 999999,
                'username' => null,
                'password' => 'test password',
                'is_subscriber' => false,
                'average_order_price' => 1678.0193,
                'registered_at' => '2000-01-28 18:10:58',
                'user_type' => 'REGULAR',
                'config' => '{is_test: false}',
            ]],
            'entityFqn' => UserEntityFixture::class,
            'expected' => new UserEntityFixture(
                999999,
                null,
                'test password',
                false,
                1678.0193,
                new DateTimeImmutable('2000-01-28 18:10:58'),
                UserTypeEnum::REGULAR,
                '{is_test: false}',
            ),
        ];
        yield [
            'queryResult' => [0 => [
                'total_consumption_90_days' => 'test 1',
                'some_01929_numeric_9182_prop_000_name_192' => 'test 2',
            ]],
            'entityFqn' => NumericPropertyNameEntityFixture::class,
            'expected' => new NumericPropertyNameEntityFixture(
                'test 1',
                'test 2',
            ),
        ];
    }

    /**
     * @covers \Bpzr\EntityAdapter\EntityAdapter::createOne
     * @covers \Bpzr\EntityAdapter\EntityAdapter::createEntity
     * @covers \Bpzr\EntityAdapter\EntityAdapter::prepareEntityReflection
     * @covers \Bpzr\EntityAdapter\EntityAdapter::generateEntityAdapterException
     */
    #[DataProvider('createOneDataProvider')]
    public function testCreateOne(array $queryResult, string $entityFqn, ?object $expected): void {
        $resultMock = $this->createMock(Result::class);

        $resultMock->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($queryResult);

        $actual = (new EntityAdapter())->createOne($entityFqn, $resultMock);

        $this->assertEquals($expected, $actual);

        if ($expected !== null) {
            $this->assertInstanceOf($entityFqn, $actual);
        }
    }

    public static function createAllThrowsDataProvider(): Generator
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
        yield [
            'entityFqn' => ProductEntityFixture::class,
            'queryResult' => [
                0 => [
                    'id' => 1,
                    'is_purchasable' => false,
                    'config' => '{env: test}',
                ],
                1 => [
                    'id' => 2,
                    'is_purchasable' => true,
                    'config' => '{env: prod}',
                ],
                2 => [
                    'id' => 3,
                    'is_purchasable' => false,
                    'config' => '{env: dev}',
                ],

            ],
            'resultKeyExtractor' => [ProductEntityFixture::class, 'isPurchasable'],
            'expectResultKeysAreNotUniqueException' => true,
        ];
        yield [
            'entityFqn' => ProductEntityFixture::class,
            'queryResult' => [
                [
                    'id' => 1,
                    'is_purchasable' => false,
                    'config' => '{env: test}',
                ],
                [
                    'id' => 1,
                    'is_purchasable' => true,
                    'config' => '{env: prod}',
                ],

            ],
            'resultKeyExtractor' => [ProductEntityFixture::class, 'getId'],
            'expectResultKeysAreNotUniqueException' => true,
        ];
    }

    /**
     * @covers \Bpzr\EntityAdapter\EntityAdapter::prepareKeyExtractMethodName
     * @covers \Bpzr\EntityAdapter\EntityAdapter::generateEntityAdapterException
     */
    #[DataProvider('createAllThrowsDataProvider')]
    public function testCreateAllThrows(
        string $entityFqn,
        array $queryResult = [],
        ?array $resultKeyExtractor = null,
        bool $expectWrongEntityException = false,
        bool $expectNonExistingMethodException = false,
        bool $expectResultKeysAreNotUniqueException = false,
    ): void {
        $resultMock = $this->createMock(Result::class);

        $resultMock->method('fetchAllAssociative')->willReturn($queryResult);

        if ($expectWrongEntityException) {
            $this->expectExceptionMessageMatches(
                '/Key extractor class method reference must be the same class as the one being hydrated/',
            );
            $this->expectException(EntityAdapterException::class);
        }

        if ($expectNonExistingMethodException) {
            $this->expectExceptionMessageMatches(
                '/Key extractor class method reference must refer to an existing method/',
            );
            $this->expectException(EntityAdapterException::class);
        }

        if ($expectResultKeysAreNotUniqueException) {
            $this->expectExceptionMessageMatches('/Result keys must be unique/');
            $this->expectException(EntityAdapterException::class);
        }

        (new EntityAdapter())->createAll($entityFqn, $resultMock, $resultKeyExtractor);
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
        yield [
            'entityFqn' => ProductEntityFixture::class,
            'queryResult' => [
                0 => [
                    'id' => '9021',
                    'is_purchasable' => 0,
                    'config' => 'test config 1',
                ],
                1 => [
                    'id' => 9022,
                    'is_purchasable' => 1,
                    'config' => 'test config 2',
                ],
                2 => [
                    'id' => '18798',
                    'is_purchasable' => 1,
                    'config' => 'test config 3',
                ],
                3 => [
                    'id' => 119821,
                    'is_purchasable' => 0,
                    'config' => 'test config 4',
                ],
            ],
            'expected' => [
                0 => new ProductEntityFixture(
                    9021,
                    false,
                    'test config 1',
                ),
                1 => new ProductEntityFixture(
                    9022,
                    true,
                    'test config 2',
                ),
                2 => new ProductEntityFixture(
                    18798,
                    true,
                    'test config 3',
                ),
                3 => new ProductEntityFixture(
                    119821,
                    false,
                    'test config 4',
                ),
            ],
            'resultKeyExtractor' => null,
            'useGeneratorThreshold' => null,
            'rowCount' => 9999,
            'willUseGenerator' => false,
            'expectedConvertorsToShouldApplyAndApplyRunTimes' => [
                IntegerValueConvertor::class => [3, 4],
                StringValueConvertor::class => [2, 4],
                BooleanValueConvertor::class => [1, 4],
            ],
        ];
        yield [
            'entityFqn' => UserEntityFixture::class,
            'queryResult' => [
                0 => [
                    'id' => '98765',
                    'username' => null,
                    'password' => 'test password',
                    'is_subscriber' => 0,
                    'average_order_price' => 0.19921,
                    'registered_at' => '2021-09-18 10:45:10',
                    'user_type' => 'PREMIUM',
                    'config' => '{subscription_auto_order: 50}',
                ],
            ],
            'expected' => [
                'test password' => new UserEntityFixture(
                    98765,
                    null,
                    'test password',
                    false,
                    0.19921,
                    new DateTimeImmutable('2021-09-18 10:45:10'),
                    UserTypeEnum::PREMIUM,
                    '{subscription_auto_order: 50}',
                ),
            ],
            'resultKeyExtractor' => [UserEntityFixture::class, 'getPassword'],
            'useGeneratorThreshold' => null,
            'rowCount' => 9999,
            'willUseGenerator' => false,
            'expectedConvertorsToShouldApplyAndApplyRunTimes' => [
                IntegerValueConvertor::class => [6, 1],
                StringValueConvertor::class => [5, 2],
                BooleanValueConvertor::class => [4, 1],
                FloatValueConvertor::class => [3, 1],
                DateTimeImmutableValueConvertor::class => [2, 1],
                BackedEnumValueConvertor::class => [1, 1],
            ],
        ];
    }

    /**
     * @covers \Bpzr\EntityAdapter\EntityAdapter::createAll
     * @covers \Bpzr\EntityAdapter\EntityAdapter::createEntity
     * @covers \Bpzr\EntityAdapter\EntityAdapter::prepareKeyExtractMethodName
     */
    #[DataProvider('createAllDataProvider')]
    public function testCreateAll(
        string $entityFqn,
        array $queryResult,
        ?array $resultKeyExtractor,
        ?int $useGeneratorThreshold,
        int $rowCount,
        bool $willUseGenerator,
        array $expectedConvertorsToShouldApplyAndApplyRunTimes = [],
        array $expected = [],
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

        $convertorMocks = [];

        foreach ($expectedConvertorsToShouldApplyAndApplyRunTimes as $expectedConvertor => $runTimes) {
            $convertorMock = $this->createMock($expectedConvertor);

            $convertorMock->expects($this->exactly($runTimes[0]))
                ->method('shouldApply')
                ->willReturnCallback(
                    static fn (string $typeName, string $entityFqn)
                        => (new $expectedConvertor())->shouldApply($typeName, $entityFqn)
                );

            $convertorMock->expects($this->exactly($runTimes[1]))
                ->method('apply')
                ->willReturnCallback(
                    static fn (string $typeName, mixed $value)
                        => (new $expectedConvertor())->apply($typeName, $value)
                );
            $convertorMocks[] = $convertorMock;
        }

        $actual = (new EntityAdapter($useGeneratorThreshold, $convertorMocks))
            ->createAll($entityFqn, $resultMock, $resultKeyExtractor);

        $this->assertEquals($expected, $actual);
    }
}