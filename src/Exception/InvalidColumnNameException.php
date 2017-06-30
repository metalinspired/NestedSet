<?php

namespace metalinspired\NestedSet\Exception;

use Throwable;

class InvalidColumnNameException extends InvalidArgumentException
{
    public function __construct($message = "Invalid column name", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
