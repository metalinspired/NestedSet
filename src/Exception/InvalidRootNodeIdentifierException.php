<?php

namespace metalinspired\NestedSet\Exception;

use Throwable;

class InvalidRootNodeIdentifierException extends InvalidArgumentException
{
    public function __construct($nodeId, $message = "", $code = 0, Throwable $previous = null)
    {
        $message = sprintf(
            "Root node identifier must be an integer, string or NULL. Instance of %s given",
            is_object($nodeId) ? get_class($nodeId) : gettype($nodeId)
        );
        parent::__construct($message, $code, $previous);
    }
}
