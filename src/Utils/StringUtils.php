<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter\Utils;

readonly class StringUtils
{
    public static function camelToSnakeCase(string $ccString): string
    {
        return preg_replace_callback('/([A-Z]+|\d+)/', function ($matches) {
            return '_' . strtolower($matches[0]);
        }, $ccString);
    }
}