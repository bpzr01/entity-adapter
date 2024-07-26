<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter\ValueConvertor\Factory;

use Bpzr\EntityAdapter\ValueConvertor\Abstract\ValueConvertorFactoryInterface;
use Bpzr\EntityAdapter\ValueConvertor\BackedEnumValueConvertor;
use Bpzr\EntityAdapter\ValueConvertor\BooleanValueConvertor;
use Bpzr\EntityAdapter\ValueConvertor\DateTimeImmutableValueConvertor;
use Bpzr\EntityAdapter\ValueConvertor\FloatValueConvertor;
use Bpzr\EntityAdapter\ValueConvertor\IntegerValueConvertor;
use Bpzr\EntityAdapter\ValueConvertor\StringValueConvertor;

class ValueConvertorFactory implements ValueConvertorFactoryInterface
{
    /** @inheritDoc */
    public function createAll(): array
    {
        return [
            new StringValueConvertor(),
            new IntegerValueConvertor(),
            new FloatValueConvertor(),
            new BooleanValueConvertor(),
            new DateTimeImmutableValueConvertor(),
            new BackedEnumValueConvertor(),
        ];
    }
}