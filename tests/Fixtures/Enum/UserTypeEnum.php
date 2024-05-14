<?php

declare(strict_types=1);

namespace Bpzr\Tests\Fixtures\Enum;

enum UserTypeEnum: string
{
    case REGULAR = 'REGULAR';
    case PREMIUM = 'PREMIUM';
}
