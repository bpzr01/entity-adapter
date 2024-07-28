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
use Bpzr\Tests\Fixture\Entity\DateEntityFixture;
use Bpzr\Tests\Fixture\Entity\InvalidDateFormatEntityFixture;
use Bpzr\Tests\Fixture\Entity\MissingDateTimeFormatAttributeEntityFixture;
use Bpzr\Tests\Fixture\Entity\MultipleAttributesEntityFixture;
use Bpzr\Tests\Fixture\Entity\NullablePropertiesEntityFixture;
use Bpzr\Tests\Fixture\Entity\NumericPropertyNameEntityFixture;
use Bpzr\Tests\Fixture\Entity\ProductEntityFixture;
use Bpzr\Tests\Fixture\Entity\SingleAttributeEntityFixture;
use Bpzr\Tests\Fixture\Entity\UnsupportedDataTypeEntityFixture;
use Bpzr\Tests\Fixture\Entity\UserEntityFixture;
use Bpzr\Tests\Fixture\Enum\UserTypeEnum;
use Bpzr\Tests\Fixture\ValueConvertor\TestValueConvertor;
use Couchbase\QueryResult;
use DateTimeImmutable;
use Doctrine\DBAL\Result;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\assertEquals;

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

    #[DataProvider('createOneWillThrowTooManyRowsDataProvider')]
    public function testCreateOneWillThrowTooManyRows(array $queryResult, string $entityFqn): void
    {
        $this->expectExceptionMessageMatches('/Query must not return more than one row/');
        $this->expectException(EntityAdapterException::class);

        (new EntityAdapter(new ValueConvertorFactory()))->createOne($entityFqn, $this->createQueryResultMock($queryResult));
    }

    public static function createOneWillThrowFailedToGetValueByColumnNameDataProvider(): Generator
    {
        yield 'empty array' => [
            'queryResult' => [[]],
            'entityFqn' => UserEntityFixture::class,
        ];
        yield 'invalid column name' => [
            'queryResult' => [
                [
                    'id' => 123,
                    'invalid_column_name' => 'column value'
                ]
            ],
            'entityFqn' => ProductEntityFixture::class,
        ];

        yield 'invalid column name cc' => [
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

    #[DataProvider('createOneWillThrowFailedToGetValueByColumnNameDataProvider')]
    public function testCreateOneWillThrowFailedToGetValueByColumnName(array $queryResult, string $entityFqn): void
    {
        $this->expectExceptionMessageMatches(
            '/Could not get value of property .* by database column .*/'
        );
        $this->expectException(EntityAdapterException::class);

        (new EntityAdapter(new ValueConvertorFactory()))->createOne($entityFqn, $this->createQueryResultMock($queryResult));
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

    #[DataProvider('createOneWillNotUseValueConvertorsDataProvider')]
    public function testCreateOneWillNotUseValueConvertors(
        array $queryResult,
        string $entityFqn,
        ?object $expected,
    ): void {
        $valueConvertorFactoryMock = $this->createMock(ValueConvertorFactory::class);
        $valueConvertorFactoryMock->method('createAll')->willReturn([]);

        $entity = (new EntityAdapter($valueConvertorFactoryMock))
            ->createOne($entityFqn, $this->createQueryResultMock($queryResult));

        $this->assertEquals($entity, $expected);
    }

    public static function createsSubscribedAttributesDataProvider(): Generator
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

    #[DataProvider('createsSubscribedAttributesDataProvider')]
    public function testCreatesSubscribedAttributes(
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

        $testIntValueConvertorMock->method('fromDb')->willReturnCallback(
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
            $this->createQueryResultMock($queryResult),
        );

        $this->assertEquals($expectedEntity, $actualEntity);
    }

    private function createQueryResultMock(array $queryResult): Result
    {
        $resultMock = $this->createMock(Result::class);
        $resultMock->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($queryResult);

        return $resultMock;
    }

    public static function createOneThrowsDataProvider(): Generator
    {
        yield [
            'queryResult' => [0 => ['enum' => 'testCase']],
            'entityFqn' => UnsupportedDataTypeEntityFixture::class,
            'expectUnsupportedDataTypeException' => true,
        ];
        yield [
            'queryResult' => [0 => ['id' => 123, 'date' => '2022-02-02 12:12:12', 'name' => 'testName']],
            'entityFqn' => MissingDateTimeFormatAttributeEntityFixture::class,
            'expectMissingDateTimeFormatAttributeException' => true,
        ];
        yield [
            'queryResult' => [0 => ['id' => 123, 'date' => 'malformed-date-string', 'name' => 'testName']],
            'entityFqn' => MissingDateTimeFormatAttributeEntityFixture::class,
            'expectMissingDateTimeFormatAttributeException' => true,
        ];
        yield [
            'queryResult' => [0 => ['date' => '2022-02-02 12:12']],
            'entityFqn' => InvalidDateFormatEntityFixture::class,
            'expectInvalidDateTimeFormatAttributeException' => true,
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
        bool $expectUnsupportedDataTypeException = false,
        bool $expectMissingDateTimeFormatAttributeException = false,
        bool $expectInvalidDateTimeFormatAttributeException = false,
    ): void {
        $resultMock = $this->createMock(Result::class);
        $resultMock->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($queryResult);

        if ($expectUnsupportedDataTypeException) {
            $this->expectExceptionMessageMatches('/Type .* is not supported/');
            $this->expectException(EntityAdapterException::class);
        }

        if ($expectMissingDateTimeFormatAttributeException) {
            $this->expectExceptionMessageMatches('/Missing .* attribute./');
            $this->expectException(EntityAdapterException::class);
        }

        if ($expectInvalidDateTimeFormatAttributeException) {
            $this->expectExceptionMessageMatches('/Failed to create DTI from format:/');
            $this->expectException(EntityAdapterException::class);
        }

        (new EntityAdapter(new ValueConvertorFactory()))->createOne($entityFqn, $resultMock);
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

        $actual = (new EntityAdapter(new ValueConvertorFactory()))->createOne($entityFqn, $resultMock);

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
        $resultMock->method('rowCount')->willReturn(count($queryResult));

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

        $resultMock->expects($this->once())->method('rowCount')->willReturn(count($queryResult));

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

    public static function subscribedAttributesDataProvider(): Generator
    {
        yield [
            'queryResult' => [
                0 => [
                    'date' => '2011-01-01',
                    'date_time' => '2011-01-01 00:00:00',
                    'year' => '2011',
                ],
                1 => [
                    'date' => '2022-02-02',
                    'date_time' => '2022-02-02 10:20:30',
                    'year' => '2022',
                ]
            ],
            'valueConvertor' => new DateTimeImmutableValueConvertor(),
        ];
    }

    #[DataProvider('subscribedAttributesDataProvider')]
    public function testSubscribedAttributes(array $queryResult, ValueConvertorInterface $valueConvertor): void
    {
        $valueConvertorFactoryMock = $this->createMock(ValueConvertorFactory::class);

        $valueConvertorFactoryMock
            ->expects($this->once())
            ->method('createAll')
            ->willReturn([$valueConvertor]);

        $entityAdapter = new EntityAdapter($valueConvertorFactoryMock);

        $resultMock = $this->createMock(Result::class);
        $resultMock->method('fetchAllAssociative')->willReturn($queryResult);
        $resultMock->method('rowCount')->willReturn(2);

        $results = $entityAdapter->createAll(DateEntityFixture::class, $resultMock);

        $this->assertSame($queryResult[0]['date'], $results[0]->getDate()->format('Y-m-d'));
        $this->assertSame($queryResult[0]['date_time'], $results[0]->getDateTime()->format('Y-m-d H:i:s'));
        $this->assertSame($queryResult[0]['year'], $results[0]->getYear()->format('Y'));
    }
}