<?php

namespace metalinspired\NestedSet\Exception;

use Throwable;

class NodeChildOrSiblingToItself extends RuntimeException
{
    public function __construct(
        $message = "Node can not be set as child/sibling to itself or own descendants",
        $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
