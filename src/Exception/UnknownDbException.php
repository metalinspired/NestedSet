<?php

namespace metalinspired\NestedSet\Exception;

use Throwable;

class UnknownDbException extends RuntimeException
{
    public function __construct($message = "Unknown database error", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
