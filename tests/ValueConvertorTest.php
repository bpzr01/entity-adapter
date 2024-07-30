<?php

declare(strict_types=1);

namespace Bpzr\Tests;

use Bpzr\EntityAdapter\Attribute\DateTimeFormat;
use Bpzr\EntityAdapter\ValueConvertor\Abstract\ValueConvertorInterface;
use Bpzr\EntityAdapter\ValueConvertor\BackedEnumValueConvertor;
use Bpzr\EntityAdapter\ValueConvertor\BooleanValueConvertor;
use Bpzr\EntityAdapter\ValueConvertor\DateTimeImmutableValueConvertor;
use Bpzr\EntityAdapter\ValueConvertor\FloatValueConvertor;
use Bpzr\EntityAdapter\ValueConvertor\IntegerValueConvertor;
use Bpzr\EntityAdapter\ValueConvertor\StringValueConvertor;
use Bpzr\Tests\Fixture\Entity\ProductEntityFixture;
use Bpzr\Tests\Fixture\Enum\UserTypeEnum;
use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ValueConvertorTest extends TestCase
{
    public static function valueConvertorWillApplyToDataProvider(): Generator
    {
        yield 'int convertor int type' => [
            'valueConvertor' => new IntegerValueConvertor(),
            'entityFqn' => '',
            'propertyTypeName' => 'int',
            'expected' => true,
        ];
        yield 'int convertor string type' => [
            'valueConvertor' => new IntegerValueConvertor(),
            'entityFqn' => '',
            'propertyTypeName' => 'string',
            'expected' => false,
        ];
        yield 'int convertor non-existing type' => [
            'valueConvertor' => new IntegerValueConvertor(),
            'entityFqn' => 'testEntityFqn',
            'propertyTypeName' => 'testPropertyName',
            'expected' => false,
        ];
        yield 'string convertor int type' => [
            'valueConvertor' => new StringValueConvertor(),
            'entityFqn' => '',
            'propertyTypeName' => 'int',
            'expected' => false,
        ];
        yield 'string convertor string type' => [
            'valueConvertor' => new StringValueConvertor(),
            'entityFqn' => '',
            'propertyTypeName' => 'string',
            'expected' => true,
        ];
        yield 'float convertor string type' => [
            'valueConvertor' => new FloatValueConvertor(),
            'entityFqn' => ProductEntityFixture::class,
            'propertyTypeName' => 'string',
            'expected' => false,
        ];
        yield 'float convertor float type' => [
            'valueConvertor' => new FloatValueConvertor(),
            'entityFqn' => '',
            'propertyTypeName' => 'float',
            'expected' => true,
        ];
        yield 'bool convertor class fqn type' => [
            'valueConvertor' => new BooleanValueConvertor(),
            'entityFqn' => '',
            'propertyTypeName' => BackedEnumValueConvertor::class,
            'expected' => false,
        ];
        yield 'bool convertor bool type' => [
            'valueConvertor' => new BooleanValueConvertor(),
            'entityFqn' => ProductEntityFixture::class,
            'propertyTypeName' => 'bool',
            'expected' => true,
        ];
        yield 'enum convertor enum type' => [
            'valueConvertor' => new BackedEnumValueConvertor(),
            'entityFqn' => '',
            'propertyTypeName' => UserTypeEnum::class,
            'expected' => true,
        ];
        yield 'enum convertor string type' => [
            'valueConvertor' => new BackedEnumValueConvertor(),
            'entityFqn' => '',
            'propertyTypeName' => 'UserTypeEnum::class',
            'expected' => false,
        ];
        yield 'DTI convertor DTI type' => [
            'valueConvertor' => new DateTimeImmutableValueConvertor(),
            'entityFqn' => 'test321',
            'propertyTypeName' => DateTimeImmutable::class,
            'expected' => true,
        ];
        yield 'DTI convertor string type' => [
            'valueConvertor' => new DateTimeImmutableValueConvertor(),
            'entityFqn' => ProductEntityFixture::class,
            'propertyTypeName' => '2022-01-01',
            'expected' => false,
        ];
    }

    #[DataProvider('valueConvertorWillApplyToDataProvider')]
    public function testValueConvertorWillApplyTo(
        ValueConvertorInterface $valueConvertor,
        string $entityFqn,
        string $propertyTypeName,
        bool $expected,
    ): void {
        $actual = $valueConvertor->shouldApply($propertyTypeName, $entityFqn);

        $this->assertSame($expected, $actual);
    }

    public static function returnsSubscribedAttributeDataProvider(): Generator
    {
        yield 'DTI convertor returns format' => [
            'valueConvertor' => new DateTimeImmutableValueConvertor(),
            'expected' => DateTimeFormat::class,
        ];
        yield 'int convertor returns null' => [
            'valueConvertor' => new IntegerValueConvertor(),
            'expected' => null,
        ];
        yield 'string convertor returns null' => [
            'valueConvertor' => new StringValueConvertor(),
            'expected' => null,
        ];
        yield 'backed enum convertor returns null' => [
            'valueConvertor' => new BackedEnumValueConvertor(),
            'expected' => null,
        ];
        yield 'bool convertor returns null' => [
            'valueConvertor' => new BooleanValueConvertor(),
            'expected' => null,
        ];
        yield 'float convertor returns null' => [
            'valueConvertor' => new FloatValueConvertor(),
            'expected' => null,
        ];
    }

    #[DataProvider('returnsSubscribedAttributeDataProvider')]
    public function testReturnsSubscribedAttribute(ValueConvertorInterface $valueConvertor, ?string $expected): void
    {
        $this->assertSame($expected, $valueConvertor->getSubscribedPropertyAttributeFqn());
    }

    public static function throwsMissingDateTimeFormatDataProvider(): Generator
    {
        yield [
            'typeName' => 'int',
            'value' => '2022-01-01',
        ];
        yield [
            'typeName' => 'string',
            'value' => 'invalidDateTime',
        ];
        yield [
            'typeName' => 'test123',
            'value' => '2022-01-01 10:12:13',
        ];
        yield [
            'typeName' => ProductEntityFixture::class,
            'value' => '2024/12/1',
        ];
        yield [
            'typeName' => ProductEntityFixture::class,
            'value' => '60/70/80',
        ];
    }

    #[DataProvider('throwsMissingDateTimeFormatDataProvider')]
    public function testThrowsMissingDateTimeFormat(string $typeName, string $value): void
    {
        $this->expectExceptionMessageMatches('/Missing .* attribute/');
        $this->expectException(RuntimeException::class);

        (new DateTimeImmutableValueConvertor())->fromDb($typeName, $value, []);
    }

    public static function throwsInvalidDateTimeFormatDataProvider(): Generator
    {
        yield [
            'typeName' => 'int',
            'value' => '2022-01-01 10',
            'subscribedAttributes' => [new DateTimeFormat('Y-m-d')],
        ];
        yield [
            'typeName' => 'int',
            'value' => '01-01-2024',
            'subscribedAttributes' => [new DateTimeFormat('d-m-y')],
        ];
        yield [
            'typeName' => 'int',
            'value' => '2022-50-70 10:23:20',
            'subscribedAttributes' => [new DateTimeFormat('Y-m-d H:i')],
        ];
        yield [
            'typeName' => 'int',
            'value' => 'invalid-date-time-string',
            'subscribedAttributes' => [new DateTimeFormat('Y-m-d H:i:s')],
        ];
    }

    #[DataProvider('throwsInvalidDateTimeFormatDataProvider')]
    public function testThrowsInvalidDateTimeFormat(string $typeName, string $value, array $subscribedAttributes): void
    {
        $this->expectExceptionMessageMatches('/Failed to create DTI from format:/');
        $this->expectException(RuntimeException::class);

        (new DateTimeImmutableValueConvertor())->fromDb($typeName, $value, $subscribedAttributes);
    }
}