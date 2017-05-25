<?php

namespace metalinspired\NestedSet\Exception;

use Throwable;

class NoTableSetException extends RuntimeException
{
    public function __construct($message = 'No table was set globally or specified as argument', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}