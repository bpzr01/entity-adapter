<?php

declare(strict_types=1);

namespace Bpzr\EntityHydrator\Utils;

readonly class StringUtils
{
    public static function camelToSnakeCase(string $ccString): string
    {
        return preg_replace_callback('/[A-Z]/', function ($matches) {
            return '_' . mb_strtolower($matches[0], 'UTF-8');
        }, $ccString);
    }
}