<?php

namespace metalinspired\NestedSet;

use metalinspired\NestedSet\Exception\InvalidArgumentException;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Sql\Sql;

abstract class AbstractNestedSet
{
    /**
     * Database adapter instance
     *
     * @var AdapterInterface
     */
    protected $adapter = null;

    /**
     * Name of a table used to execute queries
     * Can be a string or one element array
     * where key represents alias and value actual table name
     *
     * @var string|array
     */
    protected $table = null;

    /**
     * Column name for identifiers of nodes
     *
     * @var string
     */
    protected $idColumn = 'id';

    /**
     * Column name for left values of nodes
     *
     * @var string
     */
    protected $leftColumn = 'lft';

    /**
     * Column name for right values of nodes
     *
     * @var string
     */
    protected $rightColumn = 'rgt';

    /**
     * Identifier of root node
     * This is used to omit root node from results
     *
     * @var int|string
     */
    protected $rootNodeId = 1;

    /**
     * Cached statements
     *
     * @var array
     */
    protected $statements = [];

    /**
     * @var Sql
     */
    protected $sql = null;

    public function __construct(Config $config)
    {
        $this->loadConfig($config);
    }

    /**
     * Returns currently set database adapter instance
     *
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * Sets database adapter instance
     *
     * @param AdapterInterface $adapter
     * @return $this Provides a fluent interface
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->statements = [];
        $this->adapter = $adapter;
        return $this;
    }

    /**
     * Returns table name used for executing queries
     *
     * @return string|null
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Sets table name used for executing queries
     *
     * @param string|array $table Table name
     * @return $this Provides a fluent interface
     * @throws Exception\InvalidArgumentException
     */
    public function setTable($table)
    {
        if (!is_string($table) && !is_array($table)) {
            throw new InvalidArgumentException();
        }

        $this->statements = [];
        $this->table = $table;
        return $this;
    }

    /**
     * Returns name of column used for identifiers of nodes
     *
     * @return string
     */
    public function getIdColumn()
    {
        return $this->idColumn;
    }

    /**
     * Sets name of column used for identifiers of nodes
     *
     * @param string $name Id column name
     * @return $this Provides a fluent interface
     * @throws Exception\InvalidArgumentException
     */
    public function setIdColumn($name)
    {
        if (!is_string($name) || empty($name)) {
            throw new Exception\InvalidArgumentException("Invalid column name");
        }

        $this->statements = [];
        $this->idColumn = $name;

        return $this;
    }

    /**
     * Returns name of column used for left values of nodes
     *
     * @return string
     */
    public function getLeftColumn()
    {
        return $this->leftColumn;
    }

    /**
     * Sets name of column used for left values of nodes
     *
     * @param string $name
     * @return $this Provides a fluent interface
     * @throws Exception\InvalidArgumentException
     */
    public function setLeftColumn($name)
    {
        if (!is_string($name) || empty($name)) {
            throw new Exception\InvalidArgumentException("Invalid column name");
        }

        $this->statements = [];
        $this->leftColumn = $name;

        return $this;
    }

    /**
     * Returns name of column used for right values of nodes
     *
     * @return string
     */
    public function getRightColumn()
    {
        return $this->rightColumn;
    }

    /**
     * Sets name of column used for right values of nodes
     *
     * @param string $name
     * @return $this Provides a fluent interface
     * @throws Exception\InvalidArgumentException
     */
    public function setRightColumn($name)
    {
        if (!is_string($name) || empty($name)) {
            throw new Exception\InvalidArgumentException("Invalid column name");
        }

        $this->statements = [];
        $this->rightColumn = $name;

        return $this;
    }

    /**
     * Returns identifier of root node
     *
     * @return int|string
     */
    public function getRootNodeId()
    {
        return $this->rootNodeId;
    }

    /**
     * Sets identifier of root node
     *
     * @param int|string $id Root node identifier
     * @return $this Provides a fluent interface
     */
    public function setRootNodeId($id)
    {
        if (!is_int($id) && !is_string($id) || empty($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $this->statements = [];
        $this->rootNodeId = $id;

        return $this;
    }

    /**
     * Loads configuration
     *
     * @param Config $config
     * @return $this Provides a fluent interface
     */
    public function loadConfig(Config $config)
    {
        $this->statements = [];

        foreach (get_object_vars($config) as $key => $value) {
            if (method_exists($this, 'set' . $key)) {
                $this->{'set' . $key}($value);
            }
        }

        $this->sql = new Sql($this->adapter);

        return $this;
    }
}
