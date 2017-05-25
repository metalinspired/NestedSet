<?php

namespace metalinspired\NestedSet\Find;

use metalinspired\NestedSet\Exception\RuntimeException;

class FindChildren extends FindDescendants
{
    protected $depthLimit = 1;

    public function setDepthLimit($depthLimit)
    {
        throw new RuntimeException('Depth limit cannot be set for this class');
    }
}