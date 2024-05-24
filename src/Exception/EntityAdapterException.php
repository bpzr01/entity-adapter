<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter\Exception;

use Exception;
use Throwable;

final class EntityAdapterException extends Exception
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct(message: $message, previous: $previous);
    }
}