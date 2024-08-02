<?php

declare(strict_types=1);

namespace Bpzr\Tests;

use Bpzr\EntityAdapter\Attribute\DateTimeFormat;
use Bpzr\EntityAdapter\EntityAdapter;
use Bpzr\EntityAdapter\Exception\EntityAdapterException;
use Bpzr\EntityAdapter\ValueConvertor\Abstract\ValueConvertorInterface;
use Bpzr\EntityAdapter\ValueConvertor\BackedEnumValueConvertor;
use Bpzr\EntityAdapter\ValueConvertor\BooleanValueConvertor;
use Bpzr\EntityAdapter\ValueConvertor\DateTimeImmutableValueConvertor;
use Bpzr\EntityAdapter\ValueConvertor\Factory\ValueConvertorFactory;
use Bpzr\EntityAdapter\ValueConvertor\FloatValueConvertor;
use Bpzr\EntityAdapter\ValueConvertor\IntegerValueConvertor;
use Bpzr\EntityAdapter\ValueConvertor\StringValueConvertor;
use Bpzr\Tests\Fixture\Attribute\TestAttributeFixture;
use Bpzr\Tests\Fixture\Entity\MultipleAttributesEntityFixture;
use Bpzr\Tests\Fixture\Entity\NullablePropertiesEntityFixture;
use Bpzr\Tests\Fixture\Entity\NumericPropertyNameEntityFixture;
use Bpzr\Tests\Fixture\Entity\ProductEntityFixture;
use Bpzr\Tests\Fixture\Entity\SingleAttributeEntityFixture;
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
    public static function createOneWillThrowTooManyRowsDataProvider(): Generator
    {
        yield '2 rows' => [
            'queryResult' => [
                ['id' => 123],
                ['id' => 789],
            ],
            'entityFqn' => UserEntityFixture::class,
        ];
        yield 'many rows' => [
            'queryResult' => [
                ['id' => 5645],
                ['id' => 5476],
                ['id' => 45645],
                ['id' => 4564],
                ['id' => 546],
                ['id' => 645654],
                ['id' => 644],
                ['id' => 1263],
                ['id' => 534],
                ['id' => 312],
                ['id' => 45645],
            ],
            'entityFqn' => UserEntityFixture::class,
        ];
        yield 'different column names' => [
            'queryResult' => [
                ['some_column_name143' => 123],
                ['some_column_name345' => 1296],
                ['some_column_name525' => 31235],
                ['some_column_name236' => 123163],
                ['some_column_name86' => 7897869],
            ],
            'entityFqn' => ProductEntityFixture::class,
        ];
    }

    /**
     * @covers \Bpzr\EntityAdapter\EntityAdapter::createOne
     * @covers \Bpzr\EntityAdapter\EntityAdapter::generateEntityAdapterException
     */
    #[DataProvider('createOneWillThrowTooManyRowsDataProvider')]
    public function testCreateOneWillThrowTooManyRows(array $queryResult, string $entityFqn): void
    {
        $this->expectExceptionMessageMatches('/Query must not return more than one row/');
        $this->expectException(EntityAdapterException::class);

        (new EntityAdapter(new ValueConvertorFactory()))->createOne($entityFqn, $this->createQueryResultMockForCreateOne($queryResult));
    }

    public static function createOneWillThrowFailedToGetValueByColumnNameDataProvider(): Generator
    {
        yield 'empty array' => [
            'queryResult' => [[]],
            'entityFqn' => UserEntityFixture::class,
        ];
        yield 'invalid column name sc' => [
            'queryResult' => [
                [
                    'id' => 123,
                    'invalid_column_name' => 'column value'
                ]
            ],
            'entityFqn' => ProductEntityFixture::class,
        ];

        yield 'valid column name but cc' => [
            'queryResult' => [
                [
                    'id' => 123,
                    'isPurchasable' => false,
                    'config' => '{test: true}',
                ]
            ],
            'entityFqn' => ProductEntityFixture::class,
        ];
    }

    /**
     * @covers \Bpzr\EntityAdapter\EntityAdapter::createOne
     * @covers \Bpzr\EntityAdapter\EntityAdapter::generateEntityAdapterException
     * @covers \Bpzr\EntityAdapter\EntityAdapter::getRawPropValueByColumnName
     */
    #[DataProvider('createOneWillThrowFailedToGetValueByColumnNameDataProvider')]
    public function testCreateOneWillThrowFailedToGetValueByColumnName(array $queryResult, string $entityFqn): void
    {
        $this->expectExceptionMessageMatches(
            '/Could not get value of property .* by database column .*/'
        );
        $this->expectException(EntityAdapterException::class);

        (new EntityAdapter(new ValueConvertorFactory()))->createOne(
            $entityFqn,
            $this->createQueryResultMockForCreateOne($queryResult),
        );
    }

    public static function createAllWillThrowFailedToGetValueByColumnNameDataProvider(): Generator
    {
        yield 'empty array' => [
            'queryResult' => [[]],
            'entityFqn' => UserEntityFixture::class,
        ];
        yield 'invalid column name sc' => [
            'queryResult' => [
                [
                    'id' => 1,
                    'invalid_column_name' => 'column value'
                ],
                [
                    'id' => 123,
                    'invalid_column_name' => 'column value 123'
                ]
            ],
            'entityFqn' => ProductEntityFixture::class,
        ];

        yield 'valid column name but cc' => [
            'queryResult' => [
                [
                    'id' => 123,
                    'isPurchasable' => false,
                    'config' => '{test1: true}',
                ],
                [
                    'id' => 456,
                    'isPurchasable' => false,
                    'config' => '{test2: true}',
                ],
                [
                    'id' => 789,
                    'isPurchasable' => true,
                    'config' => '{test3: true}',
                ]
            ],
            'entityFqn' => ProductEntityFixture::class,
        ];
        yield 'first result has invalid column name' => [
            'queryResult' => [
                [
                    'id' => 123,
                    'invalid_column_name' => false,
                    'config' => '{test1: true}',
                ],
                [
                    'id' => 456,
                    'is_purchasable' => false,
                    'config' => '{test2: true}',
                ],
                [
                    'id' => 789,
                    'is_purchasable' => true,
                    'config' => '{test3: true}',
                ]
            ],
            'entityFqn' => ProductEntityFixture::class,
        ];
    }

    /**
     * @covers \Bpzr\EntityAdapter\EntityAdapter::createAll
     * @covers \Bpzr\EntityAdapter\EntityAdapter::generateEntityAdapterException
     * @covers \Bpzr\EntityAdapter\EntityAdapter::getRawPropValueByColumnName
     */
    #[DataProvider('createAllWillThrowFailedToGetValueByColumnNameDataProvider')]
    public function testCreateAllWillThrowFailedToGetValueByColumnName(array $queryResult, string $entityFqn): void
    {
        $this->expectExceptionMessageMatches(
            '/Could not get value of property .* by database column .*/'
        );
        $this->expectException(EntityAdapterException::class);

        $resultMock = $this->createQueryResultForCreateAll($queryResult);

        (new EntityAdapter(new ValueConvertorFactory()))->createAll($entityFqn, $resultMock);
    }

    public static function createOneWillNotUseValueConvertorsDataProvider(): Generator
    {
        yield 'all DB columns are null' => [
            'queryResult' => [
                [
                    'age' => null,
                    'name' => null,
                    'next_birthday' => null,
                ],
            ],
            'entityFqn' => NullablePropertiesEntityFixture::class,
            'expected' => new NullablePropertiesEntityFixture(null, null, null),
        ];
        yield 'does not return any rows' => [
            'queryResult' => [],
            'entityFqn' => NullablePropertiesEntityFixture::class,
            'expected' => null,
        ];
    }

    /** @covers \Bpzr\EntityAdapter\EntityAdapter::createOne */
    #[DataProvider('createOneWillNotUseValueConvertorsDataProvider')]
    public function testCreateOneWillNotUseValueConvertors(
        array $queryResult,
        string $entityFqn,
        ?object $expected,
    ): void {
        $valueConvertorFactoryMock = $this->createMock(ValueConvertorFactory::class);
        $valueConvertorFactoryMock->method('createAll')->willReturn([]);

        $entity = (new EntityAdapter($valueConvertorFactoryMock))
            ->createOne($entityFqn, $this->createQueryResultMockForCreateOne($queryResult));

        $this->assertEquals($entity, $expected);
    }

    public static function createAllWillNotUseValueConvertorsDataProvider(): Generator
    {
        yield 'all DB columns are null' => [
            'queryResult' => [
                [
                    'age' => null,
                    'name' => null,
                    'next_birthday' => null,
                ],
                [
                    'age' => null,
                    'name' => null,
                    'next_birthday' => null,
                ],
            ],
            'entityFqn' => NullablePropertiesEntityFixture::class,
            'expected' => [
                new NullablePropertiesEntityFixture(null, null, null),
                new NullablePropertiesEntityFixture(null, null, null),
            ],
        ];
        yield 'does not return any rows' => [
            'queryResult' => [],
            'entityFqn' => NullablePropertiesEntityFixture::class,
            'expected' => [],
        ];
    }

    /** @covers \Bpzr\EntityAdapter\EntityAdapter::createAll */
    #[DataProvider('createAllWillNotUseValueConvertorsDataProvider')]
    public function testCreateAllWillNotUseValueConvertors(
        array $queryResult,
        string $entityFqn,
        array $expected,
    ): void {
        $valueConvertorFactoryMock = $this->createMock(ValueConvertorFactory::class);
        $valueConvertorFactoryMock->method('createAll')->willReturn([]);

        $entities = (new EntityAdapter($valueConvertorFactoryMock))->createAll(
            $entityFqn,
            $this->createQueryResultForCreateAll($queryResult),
        );

        $this->assertEquals($entities, $expected);
    }

    public static function createOneCreatesSubscribedAttributesDataProvider(): Generator
    {
        yield 'creates empty array' => [
            'entityFqn' => ProductEntityFixture::class,
            'queryResult' => [
                [
                    'id' => 123,
                    'is_purchasable' => false,
                    'config' => '{config}',
                ],
            ],
            'expectedAttributes' => [],
            'subscribedAttributeFqn' => null,
            'expectedEntity' => new ProductEntityFixture(123, false, '{config}'),
        ];
        yield 'creates single attribute' => [
            'entityFqn' => SingleAttributeEntityFixture::class,
            'queryResult' => [
                [
                    'first_name' => 'John',
                    'order_count' => 30,
                ],
            ],
            'expectedAttributes' => [new TestAttributeFixture(123)],
            'subscribedAttributeFqn' => TestAttributeFixture::class,
            'expectedEntity' => new SingleAttributeEntityFixture('John', 153),
        ];
        yield 'creates multiple attributes' => [
            'entityFqn' => MultipleAttributesEntityFixture::class,
            'queryResult' => [
                [
                    'last_name' => 'Doe',
                    'product_count' => 900,
                ],
            ],
            'expectedAttributes' => [
                new TestAttributeFixture(1),
                new TestAttributeFixture(2),
                new TestAttributeFixture(3),
                new TestAttributeFixture(4),
            ],
            'subscribedAttributeFqn' => TestAttributeFixture::class,
            'expectedEntity' => new MultipleAttributesEntityFixture('Doe', 910),
        ];
    }

    /**
     * @covers \Bpzr\EntityAdapter\EntityAdapter::createOne
     * @covers \Bpzr\EntityAdapter\EntityAdapter::createSubscribedAttributes
     */
    #[DataProvider('createOneCreatesSubscribedAttributesDataProvider')]
    public function testCreateOneCreatesSubscribedAttributes(
        string $entityFqn,
        array $queryResult,
        array $expectedAttributes,
        ?string $subscribedAttributeFqn,
        ?object $expectedEntity,
    ): void {
        $testIntValueConvertorMock = $this->createMock(IntegerValueConvertor::class);

        $testIntValueConvertorMock->method('getSubscribedPropertyAttributeFqn')
            ->willReturn($subscribedAttributeFqn);

        $testIntValueConvertorMock->method('shouldApply')
            ->willReturnCallback(fn (string $propertyTypeName, string $entityFqn) => $propertyTypeName === 'int');

        $testIntValueConvertorMock
            ->expects($this->once())
            ->method('fromDb')
            ->willReturnCallback(
                function (string $typeName, mixed $value, array $subscribedAttributes) use ($expectedAttributes) {
                    $this->assertEquals($expectedAttributes, $subscribedAttributes);

                    return $value + array_sum(array_map(
                        fn (TestAttributeFixture $ta) => $ta->getTestValue(),
                        $subscribedAttributes,
                    ));
                },
            );

        $valueConvertorFactoryMock = $this->createMock(ValueConvertorFactory::class);
        $valueConvertorFactoryMock->method('createAll')->willReturn([
            $testIntValueConvertorMock,
            new BooleanValueConvertor(),
            new StringValueConvertor(),
        ]);

        $actualEntity = (new EntityAdapter($valueConvertorFactoryMock))->createOne(
            $entityFqn,
            $this->createQueryResultMockForCreateOne($queryResult),
        );

        $this->assertEquals($expectedEntity, $actualEntity);
    }

    public static function createAllCreatesSubscribedAttributesDataProvider(): Generator
    {
        yield 'creates empty array' => [
            'entityFqn' => ProductEntityFixture::class,
            'queryResult' => [
                [
                    'id' => 123,
                    'is_purchasable' => false,
                    'config' => '{config}',
                ],
            ],
            'expectedAttributes' => [],
            'subscribedAttributeFqn' => null,
            'expectedEntities' => [new ProductEntityFixture(123, false, '{config}')],
        ];
        yield 'creates single attribute' => [
            'entityFqn' => SingleAttributeEntityFixture::class,
            'queryResult' => [
                [
                    'first_name' => 'John',
                    'order_count' => 30,
                ],
            ],
            'expectedAttributes' => [new TestAttributeFixture(123)],
            'subscribedAttributeFqn' => TestAttributeFixture::class,
            'expectedEntities' => [new SingleAttributeEntityFixture('John', 153)],
        ];
        yield 'creates multiple attributes' => [
            'entityFqn' => MultipleAttributesEntityFixture::class,
            'queryResult' => [
                [
                    'last_name' => 'Doe',
                    'product_count' => 1000,
                ],
            ],
            'expectedAttributes' => [
                new TestAttributeFixture(1),
                new TestAttributeFixture(2),
                new TestAttributeFixture(3),
                new TestAttributeFixture(4),
            ],
            'subscribedAttributeFqn' => TestAttributeFixture::class,
            'expectedEntities' => [new MultipleAttributesEntityFixture('Doe', 1010)],
        ];
    }

    /**
     * @covers \Bpzr\EntityAdapter\EntityAdapter::createOne
     * @covers \Bpzr\EntityAdapter\EntityAdapter::createSubscribedAttributes
     */
    #[DataProvider('createAllCreatesSubscribedAttributesDataProvider')]
    public function testCreateAllCreatesSubscribedAttributes(
        string $entityFqn,
        array $queryResult,
        array $expectedAttributes,
        ?string $subscribedAttributeFqn,
        array $expectedEntities,
    ): void {
        $testIntValueConvertorMock = $this->createMock(IntegerValueConvertor::class);

        $testIntValueConvertorMock->method('getSubscribedPropertyAttributeFqn')
            ->willReturn($subscribedAttributeFqn);

        $testIntValueConvertorMock->method('shouldApply')
            ->willReturnCallback(fn (string $propertyTypeName, string $entityFqn) => $propertyTypeName === 'int');

        $testIntValueConvertorMock
            ->expects($this->once())
            ->method('fromDb')
            ->willReturnCallback(
                function (string $typeName, mixed $value, array $subscribedAttributes) use ($expectedAttributes) {
                    $this->assertEquals($expectedAttributes, $subscribedAttributes);

                    return $value + array_sum(array_map(
                        fn (TestAttributeFixture $ta) => $ta->getTestValue(),
                        $subscribedAttributes,
                    ));
                },
            );

        $valueConvertorFactoryMock = $this->createMock(ValueConvertorFactory::class);
        $valueConvertorFactoryMock->method('createAll')->willReturn([
            $testIntValueConvertorMock,
            new BooleanValueConvertor(),
            new StringValueConvertor(),
        ]);

        $actualEntities = (new EntityAdapter($valueConvertorFactoryMock))->createAll(
            $entityFqn,
            $this->createQueryResultForCreateAll($queryResult),
        );

        $this->assertEquals($expectedEntities, $actualEntities);
    }

    public static function createOneThrowsUnsupportedDataTypeDataProvider(): Generator
    {
        yield 'factory returns string convertor' => [
            'entityFqn' => UnsupportedDataTypeEntityFixture::class,
            'queryResult' => [
                [
                    'name' => 'testname321',
                    'count' => 999,
                ]
            ],
            'convertorCreateAllResult' => [new StringValueConvertor()],
            'expectedUnsupportedDataType' => 'int'
        ];

        yield 'factory returns int convertor' => [
            'entityFqn' => UnsupportedDataTypeEntityFixture::class,
            'queryResult' => [
                [
                    'name' => 'testname123',
                    'count' => 111,
                ]
            ],
            'convertorCreateAllResult' => [new IntegerValueConvertor()],
            'expectedUnsupportedDataType' => 'string'
        ];
        yield 'factory returns empty array' => [
            'entityFqn' => UnsupportedDataTypeEntityFixture::class,
            'queryResult' => [
                [
                    'name' => '',
                    'count' => 0,
                ]
            ],
            'convertorCreateAllResult' => [],
            'expectedUnsupportedDataType' => 'string'
        ];
    }

    /**
     * @covers \Bpzr\EntityAdapter\EntityAdapter::createOne
     * @covers \Bpzr\EntityAdapter\EntityAdapter::selectValueConvertor
     * @covers \Bpzr\EntityAdapter\EntityAdapter::generateEntityAdapterException
     */
    #[DataProvider('createOneThrowsUnsupportedDataTypeDataProvider')]
    public function testCreateOneThrowsUnsupportedDataType(
        string $entityFqn,
        array $queryResult,
        array $convertorCreateAllResult,
        string $expectedUnsupportedDataType,
    ): void {
        $resultMock = $this->createQueryResultMockForCreateOne($queryResult);
        $valueConvertorFactoryMock = $this->createMock(ValueConvertorFactory::class);
        $valueConvertorFactoryMock->method('createAll')->willReturn($convertorCreateAllResult);

        $this->expectExceptionMessage("Type {$expectedUnsupportedDataType} is not supported");
        $this->expectException(EntityAdapterException::class);

        (new EntityAdapter($valueConvertorFactoryMock))->createOne($entityFqn, $resultMock);
    }

    public static function createAllThrowsUnsupportedDataTypeDataProvider(): Generator
    {
        yield 'factory returns string convertor' => [
            'entityFqn' => UnsupportedDataTypeEntityFixture::class,
            'queryResult' => [
                [
                    'name' => 'testname123',
                    'count' => 998,
                ],
                [
                    'name' => 'testname321',
                    'count' => 999,
                ]
            ],
            'convertorCreateAllResult' => [new StringValueConvertor()],
            'expectedUnsupportedDataType' => 'int'
        ];

        yield 'factory returns int convertor' => [
            'entityFqn' => UnsupportedDataTypeEntityFixture::class,
            'queryResult' => [
                [
                    'name' => 'testname567',
                    'count' => 222,
                ],
                [
                    'name' => 'testname765',
                    'count' => 111,
                ]
            ],
            'convertorCreateAllResult' => [new IntegerValueConvertor()],
            'expectedUnsupportedDataType' => 'string'
        ];
        yield 'factory returns empty array' => [
            'entityFqn' => UnsupportedDataTypeEntityFixture::class,
            'queryResult' => [
                [
                    'name' => '',
                    'count' => 0,
                ]
            ],
            'convertorCreateAllResult' => [],
            'expectedUnsupportedDataType' => 'string'
        ];
    }

    /**
     * @covers \Bpzr\EntityAdapter\EntityAdapter::createAll
     * @covers \Bpzr\EntityAdapter\EntityAdapter::selectValueConvertor
     * @covers \Bpzr\EntityAdapter\EntityAdapter::generateEntityAdapterException
     */
    #[DataProvider('createAllThrowsUnsupportedDataTypeDataProvider')]
    public function testCreateAllThrowsUnsupportedDataType(
        string $entityFqn,
        array $queryResult,
        array $convertorCreateAllResult,
        string $expectedUnsupportedDataType,
    ): void {
        $resultMock = $this->createQueryResultForCreateAll($queryResult);
        $valueConvertorFactoryMock = $this->createMock(ValueConvertorFactory::class);
        $valueConvertorFactoryMock->method('createAll')->willReturn($convertorCreateAllResult);

        $this->expectExceptionMessage("Type {$expectedUnsupportedDataType} is not supported");
        $this->expectException(EntityAdapterException::class);

        (new EntityAdapter($valueConvertorFactoryMock))->createAll($entityFqn, $resultMock);
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

    /** @covers \Bpzr\EntityAdapter\EntityAdapter::createOne */
    #[DataProvider('createOneDataProvider')]
    public function testCreateOne(array $queryResult, string $entityFqn, ?object $expected): void {
        $resultMock = $this->createMock(Result::class);

        $resultMock->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($queryResult);

        $actual = (new EntityAdapter(new ValueConvertorFactory()))->createOne($entityFqn, $resultMock);

        $this->assertEquals($expected, $actual);

        if ($expected !== null) {
            $this->assertInstanceOf($entityFqn, $actual);
        }
    }

    public static function createAllThrowsWrongEntityDataProvider(): Generator
    {
        yield [
            'entityFqn' => UserEntityFixture::class,
            'resultKeyExtractor' => [ProductEntityFixture::class, 'getConfig'],
        ];
        yield [
            'entityFqn' => UserEntityFixture::class,
            'resultKeyExtractor' => ['invalid class FQN', 'getConfig'],
        ];
    }

    /**
     * @covers \Bpzr\EntityAdapter\EntityAdapter::createAll
     * @covers \Bpzr\EntityAdapter\EntityAdapter::prepareKeyExtractMethodName
     * @covers \Bpzr\EntityAdapter\EntityAdapter::generateEntityAdapterException
     */
    #[DataProvider('createAllThrowsWrongEntityDataProvider')]
    public function testCreateAllThrowsWrongEntity(string $entityFqn, array $resultKeyExtractor): void
    {
        $this->expectExceptionMessageMatches(
            '/Key extractor class method reference must be the same class as the one being hydrated/',
        );
        $this->expectException(EntityAdapterException::class);

        $resultMock = $this->createMock(Result::class);

        (new EntityAdapter(new ValueConvertorFactory()))->createAll($entityFqn, $resultMock, $resultKeyExtractor);
    }

    public static function createAllThrowsNonExistingMethodDataProvider(): Generator
    {
        yield [
            'entityFqn' => UserEntityFixture::class,
            'resultKeyExtractor' => [UserEntityFixture::class, 'someMethodThatDoesNotExist'],
        ];
        yield [
            'entityFqn' => UserEntityFixture::class,
            'resultKeyExtractor' => [UserEntityFixture::class, ''],
        ];
    }

    /**
     * @covers \Bpzr\EntityAdapter\EntityAdapter::createAll
     * @covers \Bpzr\EntityAdapter\EntityAdapter::prepareKeyExtractMethodName
     * @covers \Bpzr\EntityAdapter\EntityAdapter::generateEntityAdapterException
     */
    #[DataProvider('createAllThrowsNonExistingMethodDataProvider')]
    public function testCreateAllThrowsNonExistingMethod(string $entityFqn, array $resultKeyExtractor): void
    {
        $this->expectExceptionMessageMatches(
            '/Key extractor class method reference must refer to an existing method/',
        );
        $this->expectException(EntityAdapterException::class);

        $resultMock = $this->createMock(Result::class);

        (new EntityAdapter(new ValueConvertorFactory()))->createAll($entityFqn, $resultMock, $resultKeyExtractor);
    }

    public static function throwsResultKeysAreNotUniqueExceptionDataProvider(): Generator
    {
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
        ];
        yield [
            'entityFqn' => ProductEntityFixture::class,
            'queryResult' => [
                [
                    'id' => 1,
                    'is_purchasable' => false,
                    'config' => '',
                ],
                [
                    'id' => 1,
                    'is_purchasable' => true,
                    'config' => '',
                ],

            ],
            'resultKeyExtractor' => [ProductEntityFixture::class, 'getConfig'],
        ];
    }

    #[DataProvider('throwsResultKeysAreNotUniqueExceptionDataProvider')]
    public function testThrowsResultKeysAreNotUniqueException(
        string $entityFqn,
        array $queryResult,
        array $resultKeyExtractor,
    ): void {
        $resultMock = $this->createMock(Result::class);
        $resultMock->method('fetchAllAssociative')->willReturn($queryResult);
        $resultMock->method('rowCount')->willReturn(count($queryResult));

        $this->expectExceptionMessageMatches('/Result keys must be unique/');
        $this->expectException(EntityAdapterException::class);

        (new EntityAdapter(new ValueConvertorFactory()))->createAll($entityFqn, $resultMock, $resultKeyExtractor);
    }

    public static function createAllDataProvider(): Generator
    {
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
            'willUseGenerator' => false,
            'expectedConvertorsToRunTimes' => [
                IntegerValueConvertor::class => ['shouldApply' => 3, 'fromDb' => 4],
                StringValueConvertor::class => ['shouldApply' => 2, 'fromDb' => 4],
                BooleanValueConvertor::class => ['shouldApply' => 1, 'fromDb' => 4],
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
            'useGeneratorThreshold' => 1,
            'willUseGenerator' => true,
            'expectedConvertorsToRunTimes' => [
                IntegerValueConvertor::class => ['shouldApply' => 6, 'fromDb' => 1],
                StringValueConvertor::class => ['shouldApply' => 5, 'fromDb' => 2],
                BooleanValueConvertor::class => ['shouldApply' => 4, 'fromDb' => 1],
                FloatValueConvertor::class => ['shouldApply' => 3, 'fromDb' => 1],
                DateTimeImmutableValueConvertor::class => ['shouldApply' => 2, 'fromDb' => 1],
                BackedEnumValueConvertor::class => ['shouldApply' => 1, 'fromDb' => 1],
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
        bool $willUseGenerator,
        array $expectedConvertorsToRunTimes,
        array $expected = [],
    ): void {
        $resultMock = $this->createMock(Result::class);

        $resultMock
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(count($queryResult));

        if (count($queryResult) !== 0) {
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
        }

        $convertorMocks = [];

        /** @var class-string<ValueConvertorInterface> $expectedConvertor */
        foreach ($expectedConvertorsToRunTimes as $expectedConvertor => $runTimes) {
            $convertorMock = $this->createMock($expectedConvertor);

            $convertorMock->expects($this->exactly($runTimes['shouldApply']))
                ->method('shouldApply')
                ->willReturnCallback(
                    static fn (string $typeName, string $entityFqn)
                        => (new $expectedConvertor())->shouldApply($typeName, $entityFqn)
                );

            $convertorMock->expects($this->exactly($runTimes['fromDb']))
                ->method('fromDb')
                ->willReturnCallback(
                    static fn (string $typeName, mixed $value)
                        => (new $expectedConvertor())->fromDb($typeName, $value, [new DateTimeFormat('Y-m-d H:i:s')])
                );
            $convertorMocks[] = $convertorMock;
        }

        $valueConvertorFactoryMock = $this->createMock(ValueConvertorFactory::class);
        $valueConvertorFactoryMock
            ->expects($this->once())
            ->method('createAll')
            ->willReturn($convertorMocks);

        $actual = (new EntityAdapter($valueConvertorFactoryMock, $useGeneratorThreshold))
            ->createAll($entityFqn, $resultMock, $resultKeyExtractor);

        $this->assertEquals($expected, $actual);
    }

    private function createQueryResultMockForCreateOne(array $queryResult): Result
    {
        $resultMock = $this->createMock(Result::class);
        $resultMock
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($queryResult);

        return $resultMock;
    }

    private function createQueryResultForCreateAll(array $queryResult): Result
    {
        $resultMock = $this->createMock(Result::class);

        $resultCount = count($queryResult);

        if ($resultCount !== 0) {
            $resultMock
                ->expects($this->once())
                ->method('fetchAllAssociative')
                ->willReturn($queryResult);
        }

        $resultMock
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(count($queryResult));

        return $resultMock;
    }
}