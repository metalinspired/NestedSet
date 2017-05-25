<?php

namespace metalinspired\NestedSet\Find;

trait DepthLimitTrait
{
    /**
     * @var int
     */
    protected $depthLimit = null;

    public function getDepthLimit()
    {
        return $this->depthLimit;
    }

    public function setDepthLimit($depthLimit)
    {
        $this->statement = null;
        $this->depthLimit = $depthLimit;
        return $this;
    }
}