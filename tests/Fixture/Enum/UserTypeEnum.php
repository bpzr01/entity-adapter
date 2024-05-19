<?php

declare(strict_types=1);

namespace Bpzr\Tests\Fixture\Enum;

enum UserTypeEnum: string
{
    case REGULAR = 'REGULAR';
    case PREMIUM = 'PREMIUM';
}
