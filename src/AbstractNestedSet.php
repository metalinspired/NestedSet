<?php

namespace metalinspired\NestedSet;

use PDO;

abstract class AbstractNestedSet
{
    /**
     * Default fetch style
     */
    const FETCH_DEFAULT = PDO::FETCH_ASSOC;

    /**
     * Fetch mode constants
     */
    const FETCH_MODE_SINGLE = 1,
        FETCH_MODE_ALL = 2;

    /**
     * PDO instance
     *
     * @var PDO
     */
    protected $pdo;

    /**
     * Name of a driver that PDO instance is using
     *
     * @var string
     */
    protected $driverName;

    /**
     * Name of a table used to execute queries
     *
     * @var string
     */
    protected $table;

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
     * NestedSet constructor
     *
     * @param PDO $pdo
     * @param string|null $table
     * @param array $options
     * @throws Exception\InvalidArgumentException
     */
    public function __construct(PDO $pdo = null, $table = null, array $options = null)
    {
        if (null !== $pdo) {
            $this->setPdo($pdo);
        }

        if (null !== $table) {
            $this->setTable($table);
        }

        if (null !== $options) {
            $this->setOptions($options);
        }
    }

    /**
     * Returns instance of database adapter
     *
     * @return PDO|null
     *
     * @since
     */
    public function getPdo()
    {
        return $this->pdo;
    }

    /**
     * Sets PDO instance to be used
     *
     * @param PDO $pdo
     * @return void
     */
    public function setPdo(PDO $pdo)
    {
        $driverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if (!in_array($driverName, ['mysql', 'dblib', 'sqlite', 'oci'])) {
            throw new Exception\RuntimeException('Driver not supported');
        }

        $this->pdo = $pdo;
        $this->driverName = $driverName;
    }

    /**
     * Returns table name used for executing queries
     *
     * @param string|null $table If provided argument will be tested for validity and returned instead of globally set table name
     * @return string|null
     */
    public function getTable($table = null)
    {
        if (null !== $table) {
            if (!is_string($table)) {
                throw new Exception\InvalidArgumentException(sprintf(
                    "Method expects a string for table name. Instance of %s given",
                    is_object($table) ? get_class($table) : gettype($table)
                ));
            }

            if (empty($table)) {
                throw new Exception\InvalidArgumentException('Name of a table can not be a empty string');
            }

            return $table;
        }

        return $this->table;
    }

    /**
     * Sets table name used for executing queries
     *
     * @param string $table
     * @throws Exception\InvalidArgumentException
     */
    public function setTable($table)
    {
        $this->table = $this->getTable($table);
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
     * @param string $name
     * @throws Exception\InvalidArgumentException
     */
    public function setIdColumn($name)
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

        $this->idColumn = $name;
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
    }

    /**
     * Sets options defined in array
     *
     * @param array $options Array of options with following keys supported:
     *                          pdo: PDO instance
     *                          table: Table name used for executing queries
     *                          id_column: Name of column used for identifiers of nodes
     *                          left_column: Name of column used for left values of nodes
     *                          right_column: Name of column used for right values of nodes
     */
    public function setOptions(array $options)
    {
        if (array_key_exists('pdo', $options) && null !== $options['db']) {
            $this->setPdo($options['pdo']);
        }

        if (array_key_exists('table', $options) && null !== $options['table']) {
            $this->setTable($options['table']);
        }

        if (array_key_exists('id_column', $options) && null !== $options['id_column']) {
            $this->setIdColumn($options['id_column']);
        }

        if (array_key_exists('left_column', $options) && null !== $options['left_column']) {
            $this->setLeftColumn($options['left_column']);
        }

        if (array_key_exists('right_column', $options) && null !== $options['right_column']) {
            $this->setRightColumn($options['right_column']);
        }
    }

    /**
     * Quotes column/table name
     *
     * @param $name
     * @return string
     */
    public function quoteName($name)
    {
        switch ($this->driverName) {
            case 'mysql':
                $name = '`' . str_replace('`', '``', $name) . '`';
                break;
            case 'dblib': // MS SQL Server
            case 'sqlite':
                $name = '[' . str_replace(']', ']]', $name) . ']';
                break;
            case 'oci':
                $name = '"' . str_replace('"', '""', $name) . '"';
                break;
            default:
                throw new Exception\RuntimeException('No PDO instance is set');
        }

        return $name;
    }

    /**
     * Quotes a value
     *
     * @param $value
     * @return string
     */
    public function quoteValue($value)
    {
        switch ($value) {
            case is_int($value):
                $paramType = PDO::PARAM_INT;
                break;
            case is_bool($value):
                $paramType = PDO::PARAM_BOOL;
                break;
            case is_null($value):
                $paramType = PDO::PARAM_NULL;
                break;
            default:
                $paramType = PDO::PARAM_STR;
        }
        return $this->pdo->quote($value, $paramType);
    }

    /**
     * @param string $query
     * @return \PDOStatement
     * @throws Exception\RuntimeException
     */
    protected function prepareStatement($query)
    {
        $statement = $this->pdo->prepare($query);

        if (false === $statement) {
            throw new Exception\RuntimeException('Could not create SQL statement');
        }

        return $statement;
    }

    /**
     * @param string $query
     * @param int $fetchStyle
     * @param int $fetchMode
     * @return mixed
     * @throws Exception\RuntimeException
     */
    protected function executeSelect($query, $fetchStyle = self::FETCH_DEFAULT, $fetchMode = self::FETCH_MODE_SINGLE)
    {
        if (!is_int($fetchStyle)) {
            throw new Exception\InvalidArgumentException('$fetchStyle must be one of PDO::FETCH_* constants');
        }

        if (!is_int($fetchStyle) || $fetchMode !== self::FETCH_MODE_SINGLE && $fetchMode !== self::FETCH_MODE_ALL) {
            throw new Exception\InvalidArgumentException('$fetchMode must be FETCH_MODE_SINGLE or FETCH_MODE_ALL');
        }

        $statement = $this->prepareStatement($query);
        $execution = $statement->execute();

        if (false === $execution) {
            throw new Exception\RuntimeException('Could not execute select query');
        }

        if ($fetchMode === self::FETCH_MODE_SINGLE) {
            $result = $statement->fetch($fetchStyle);
        } else {
            $result = $statement->fetchAll($fetchStyle);
        }

        if (false === $result) {
            throw new Exception\RuntimeException('Could not fetch data');
        }

        return $result;
    }
}