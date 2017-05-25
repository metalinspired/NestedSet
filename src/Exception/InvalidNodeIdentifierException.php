<?php

namespace metalinspired\NestedSet\Exception;

use Throwable;

class InvalidNodeIdentifierException extends InvalidArgumentException
{
    public function __construct($nodeId, $nodeType = "Node", $message = "", $code = 0, Throwable $previous = null)
    {
        $message = sprintf(
            "%s identifier must be an integer. Instance of %s given",
            $nodeType,
            is_object($nodeId) ? get_class($nodeId) : gettype($nodeId)
        );
        parent::__construct($message, $code, $previous);
    }
}