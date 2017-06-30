<?php

namespace metalinspired\NestedSet;

trait HybridNestedSetTrait
{
    /**
     * @var string
     */
    protected $parentColumn = 'parent';

    /**
     * @var string
     */
    protected $orderingColumn = 'ordering';

    /**
     * @var string
     */
    protected $depthColumn = 'depth';

    /**
     * @return string
     */
    public function getParentColumn()
    {
        return $this->parentColumn;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setParentColumn($name)
    {
        $this->parentColumn = $this->checkColumnName($name);
        $this->statements = [];

        return $this;
    }

    /**
     * @return string
     */
    public function getOrderingColumn()
    {
        return $this->orderingColumn;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setOrderingColumn($name)
    {
        $this->orderingColumn = $this->checkColumnName($name);
        $this->statements = [];

        return $this;
    }

    /**
     * @return string
     */
    public function getDepthColumn()
    {
        return $this->depthColumn;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setDepthColumn($name)
    {
        $this->depthColumn = $this->checkColumnName($name);
        $this->statements = [];

        return $this;
    }


}
