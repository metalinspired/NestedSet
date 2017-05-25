<?php

namespace metalinspired\NestedSet;

use Zend\Db\Sql\Sql;

abstract class AbstractNestedSet
{
    /**
     * @var Config
     */
    protected $config = null;

    /**
     * @var Sql
     */
    protected $sql = null;

    public function __construct(Config $config)
    {
        $this->setConfig($config);
    }

    protected function setConfig(Config $config)
    {
        $this->config = clone $config;
        $this->sql = new Sql($this->config->getAdapter());
        return $this;
    }
}
