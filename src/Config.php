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
     * @var AdapterInterface
     */
    public $adapter = null;

    /**
     * Name of a table used to execute queries
     *
     * @var string
     */
    public $table = null;

    /**
     * Column name for identifiers of nodes
     *
     * @var string
     */
    public $idColumn = 'id';

    /**
     * Column name for left values of nodes
     *
     * @var string
     */
    public $leftColumn = 'lft';

    /**
     * Column name for right values of nodes
     *
     * @var string
     */
    public $rightColumn = 'rgt';

    /**
     * Identifier of root node
     * This is used to omit root node from results
     *
     * @var int
     */
    public $rootNodeId = 1;

    /**
     * @var array
     */
    public $columns = [Select::SQL_STAR];

    /**
     * @var bool
     */
    public $includeSearchingNode = false;

    /**
     * @var Join
     */
    public $joins = null;

    /**
     * @var int
     */
    public $depthLimit = null;

    public function __construct()
    {
        $this->joins = new Join();
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
        $config = new self();
        $config->adapter = $adapter;
        return $config;
    }
}
