<?php

namespace metalinspired\NestedSet;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Adapter\Driver\DriverInterface;
use Zend\Db\Adapter\Driver\Pdo\Pdo as ZendPdo;
use Zend\Db\Sql\Join;
use Zend\Db\Sql\Select;

class Config
{
    /**
     * @see AbstractNestedSet::$adapter
     * @var AdapterInterface
     */
    public $adapter = null;

    /**
     * @see AbstractNestedSet::$table
     * @var string|array
     */
    public $table = null;

    /**
     * @see AbstractNestedSet::$idColumn
     * @var string
     */
    public $idColumn = 'id';

    /**
     * @see AbstractNestedSet::$leftColumn
     * @var string
     */
    public $leftColumn = 'lft';

    /**
     * @see AbstractNestedSet::$rightColumn
     * @var string
     */
    public $rightColumn = 'rgt';

    /**
     * @see AbstractNestedSet::$rootNodeId
     * @var int|string|null
     */
    public $rootNodeId;

    /**
     * @see Find::$columns
     * @var array
     */
    public $columns = [Select::SQL_STAR];

    /**
     * @see Find::$includeSearchingNode
     * @var bool
     */
    public $includeSearchingNode = false;

    /**
     * @see Find::$joins
     * @var Join
     */
    public $joins = null;

    /**
     * @see Find::$depthLimit
     * @var int
     */
    public $depthLimit = null;

    public function __construct()
    {
        $this->joins = new Join();
    }

    /**
     * Creates Config object instance with database adapter created from provided DSN data
     *
     * @param string      $dsn
     * @param string|null $username
     * @param string|null $password
     * @return Config
     */
    public static function createWithDsn($dsn, $username = null, $password = null)
    {
        $pdo = new \PDO($dsn, $username, $password);
        return self::createWithPdo($pdo);
    }

    /**
     * Creates Config object instance with database adapter created with provided PDO instance
     *
     * @param \PDO $pdo
     * @return Config
     */
    public static function createWithPdo(\PDO $pdo)
    {
        $driver = new ZendPdo($pdo);
        return self::createWithDriver($driver);
    }

    /**
     * Creates Config object instance with database adapter created with provided Zend\DB\Adapter\Driver\* instance
     *
     * @param DriverInterface $driver
     * @return Config
     */
    public static function createWithDriver(DriverInterface $driver)
    {
        $adapter = new Adapter($driver);
        $config = new self();
        $config->adapter = $adapter;
        return $config;
    }
}
