<?php

namespace metalinspired\NestedSet\Find;

interface DepthLimitInterface
{
    public function getDepthLimit();

    public function setDepthLimit($depthLimit);
}