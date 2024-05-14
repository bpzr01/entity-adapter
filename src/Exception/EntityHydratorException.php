<?php

declare(strict_types=1);

namespace Bpzr\EntityHydrator\Exception;

use Exception;
use Throwable;

final class EntityHydratorException extends Exception
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct(message: $message, previous: $previous);
    }
}