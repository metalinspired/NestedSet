<?php

namespace metalinspired\NestedSet;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Adapter\Driver\DriverInterface;
use Zend\Db\Adapter\Driver\Pdo\Pdo as ZendPdo;
use Zend\Db\Sql\Select;

class Config
{
    /**
     * @var AdapterInterface
     */
    protected $adapter = null;

    /**
     * Name of a table used to execute queries
     *
     * @var string
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
     * @var int
     */
    protected $rootNodeId = 1;

    /**
     * @var array
     */
    protected $columns = [Select::SQL_STAR];

    public function __construct(AdapterInterface $adapter = null)
    {
        if ($adapter) {
            $this->adapter = $adapter;
        }
    }

    /**
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * @param AdapterInterface $adapter
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
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
     * @param string $table Table name
     * @return $this Provides a fluent interface
     * @throws Exception\InvalidArgumentException
     */
    public function setTable($table)
    {
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
        if (!is_string($name)) {
            throw new Exception\InvalidArgumentException(
                sprintf(
                    "Method expects a string as column name. Instance of %s given",
                    is_object($name) ? get_class($name) : gettype($name)
                )
            );
        }

        if (empty($name)) {
            throw new Exception\InvalidArgumentException('Column name can not be empty string');
        }

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
        if (!is_string($name)) {
            throw new Exception\InvalidArgumentException(sprintf(
                "Method expects a string as column name. Instance of %s given",
                is_object($name) ? get_class($name) : gettype($name)
            ));
        }

        if (empty($name)) {
            throw new Exception\InvalidArgumentException('Column name can not be empty string');
        }

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
        if (!is_string($name)) {
            throw new Exception\InvalidArgumentException(sprintf(
                "Method expects a string as column name. Instance of %s given",
                is_object($name) ? get_class($name) : gettype($name)
            ));
        }

        if (empty($name)) {
            throw new Exception\InvalidArgumentException('Column name can not be empty string');
        }

        $this->rightColumn = $name;

        return $this;
    }

    /**
     * Returns identifier of root node
     *
     * @return int
     */
    public function getRootNodeId()
    {
        return $this->rootNodeId;
    }

    /**
     * Sets identifier of root node
     *
     * @param int $id Root node identifier
     *
     * @return $this Provides a fluent interface
     */
    public function setRootNodeId($id)
    {
        if (!is_int($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }
        $this->rootNodeId = $id;
        return $this;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * @param array $columns
     */
    public function setColumns(array $columns)
    {
        $this->columns = $columns;
    }

    public static function createWithDsn($dsn, $username = null, $password = null)
    {
        $pdo = new \PDO($dsn, $username, $password);
        return self::createWithPdo($pdo);
    }

    public static function createWithPdo(\PDO $pdo)
    {
        $driver = new ZendPdo($pdo);
        return self::createWithDriver($driver);
    }

    public static function createWithDriver(DriverInterface $driver)
    {
        $adapter = new Adapter($driver);
        return new self($adapter);
    }
}
