<?php

namespace metalinspired\NestedSet\Exception;

use Throwable;

class NoPDOInstanceSetException extends RuntimeException
{
    public function __construct($message = "No PDO instance is set", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}