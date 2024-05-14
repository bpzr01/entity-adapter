<?php

declare(strict_types=1);

namespace Bpzr\Tests;

use Bpzr\EntityHydrator\EntityHydrator;
use Bpzr\EntityHydrator\Exception\EntityHydratorException;
use Bpzr\Tests\Fixtures\Entity\NonConstructibleEntityFixture;
use Bpzr\Tests\Fixtures\Entity\NonInstantiableEntityFixture;
use Bpzr\Tests\Fixtures\Entity\UserEntityFixture;
use Bpzr\Tests\Fixtures\Enum\UserTypeEnum;
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
    }

    #[DataProvider('createOneDataProvider')]
    public function testCreateOne(
        array $queryResult,
        string $entityFqn,
        bool $expectTooManyRowsException = false,
        bool $expectEntityIsNotInstantiableException = false,
        bool $expectEntityIsNotConstructibleException = false,
        ?UserEntityFixture $expected = null,
    ): void {
        $resultMock = $this->createMock(Result::class);

        $resultMock->method('fetchAllAssociative')
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

        $entity = (new EntityHydrator())->createOne($entityFqn, $resultMock);

        $this->assertEquals($expected, $entity);
    }
}