<?php

namespace metalinspired\NestedSet\Exception;

use Throwable;

class RootNodeNotDetectedException extends RuntimeException
{
    public function __construct($message = "Could not detect root node", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
