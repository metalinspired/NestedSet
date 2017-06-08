<?php

namespace metalinspired\NestedSet\Exception;

use Throwable;

class NodeIsOwnChildException extends RuntimeException
{
    public function __construct($message = "Node can not be its own child", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
