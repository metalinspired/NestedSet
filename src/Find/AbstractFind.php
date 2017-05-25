<?php

namespace metalinspired\NestedSet\Find;

use metalinspired\NestedSet\AbstractNestedSet;
use metalinspired\NestedSet\Config;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\Adapter\Driver\StatementInterface;
use Zend\Db\Sql\Join;
use Zend\Db\Sql\Select;

//TODO: Implement error checking in inheriting classes
abstract class AbstractFind extends AbstractNestedSet
{
    /**
     * Columns
     *
     * @var array
     */
    protected $columns = ['*'];

    /**
     * @var bool
     */
    protected $includeSearchingNode = false;

    /**
     * Cached statement
     *
     * @var StatementInterface
     */
    protected $statement = null;

    /**
     * @var array
     */
    protected $joins = [];

    public function __construct(Config $config)
    {
        parent::__construct($config);
        $this->joins = new Join();
    }

    /**
     * Returns which columns to fetch from table
     *
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Sets which columns to fetch from table
     * You should use this function only before any results were returned
     * since it will invalidate statement cache
     *
     * @param array $columns Columns array
     *
     * @return $this Provides a fluent interface
     */
    public function setColumns(array $columns)
    {
        $this->statement = null;
        $this->columns = $columns;
        return $this;
    }

    /**
     * @return bool
     */
    public function getIncludeSearchingNode()
    {
        return $this->includeSearchingNode;
    }

    /**
     * @param bool $includeSearchingNode
     * @return $this Provides a fluent interface
     */
    public function setIncludeSearchingNode($includeSearchingNode)
    {
        $this->statement = null;
        $this->includeSearchingNode = $includeSearchingNode;
        return $this;
    }

    /**
     * Check if statement is cached
     *
     * @return bool
     */
    public function isCached()
    {
        return (bool)$this->statement;
    }

    /**
     * Executes query and return results
     *
     * @param int $id Node identifier
     * @return ResultInterface
     */
    abstract public function find($id);

    /**
     * @return StatementInterface
     */
    abstract protected function buildStatement();

    protected function setConfig(Config $config)
    {
        parent::setConfig($config);
        $this->statement = null;
        $this->columns = $this->config->getColumns();
        return $this;
    }

    public function join($name, $on, $columns = [Select::SQL_STAR], $type = Join::JOIN_INNER)
    {
        $this->statement = null;
        $this->joins[] = [
            'name'    => $name,
            'on'      => $on,
            'columns' => $columns,
            'type'    => $type ? $type : Join::JOIN_INNER
        ];
        return $this;
    }

    public function resetJoins()
    {
        $this->joins = [];
        return $this;
    }

    /**
     * Allows object to be called as function to execute query
     *
     * @param int $id
     * @return mixed
     * @see find
     */
    public function __invoke($id)
    {
        return $this->find($id);
    }
}
