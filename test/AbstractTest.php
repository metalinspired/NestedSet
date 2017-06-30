<?php

namespace metalinspired\NestedSetTest;

use PDO;
use PHPUnit\DbUnit\DataSet\CompositeDataSet;
use PHPUnit\DbUnit\DataSet\ITable;
use PHPUnit\Framework\TestCase;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\DbUnit\Database\Connection;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Stdlib\ArrayUtils;

abstract class AbstractTest extends TestCase
{
    use TestCaseTrait;

    const DB_DSN = 'DB_DSN',
        DB_USER = 'DB_USER',
        DB_PASSWORD = 'DB_PASSWORD',
        DB_NAME = 'DB_NAME',
        DB_TABLE = 'DB_TABLE',
        DB_HYBRID_TABLE = 'DB_HYBRID_TABLE';

    /**
     * Only instantiate pdo once for test clean-up/fixture load
     *
     * @var PDO
     */
    static protected $pdo = null;

    /**
     * Only instantiate PHPUnit_Extensions_Database_DB_IDatabaseConnection once per test
     *
     * @var Connection
     */
    protected $conn = null;

    /**
     * @return Connection
     */
    final public function getConnection()
    {
        if (null === $this->conn) {
            $this->conn = $this->createDefaultDBConnection(self::$pdo, $GLOBALS[self::DB_NAME]);
        }

        return $this->conn;
    }

    public static function setUpBeforeClass()
    {
        // create PDO object
        self::$pdo = new PDO($GLOBALS[self::DB_DSN], $GLOBALS[self::DB_USER], $GLOBALS[self::DB_PASSWORD]);

        // create DB table
        try {
            self::$pdo->beginTransaction();
            self::$pdo->exec(
                "DROP TABLE IF EXISTS {$GLOBALS[self::DB_TABLE]};" .
                "CREATE TABLE {$GLOBALS[self::DB_TABLE]} (" .
                "id int(4) NOT NULL," .
                "lft int(4) NOT NULL," .
                "rgt int(4) NOT NULL," .
                "value varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT ''" .
                ") ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;" .
                "ALTER TABLE {$GLOBALS[self::DB_TABLE]} ADD PRIMARY KEY(id);" .
                "ALTER TABLE {$GLOBALS[self::DB_TABLE]} " .
                "MODIFY id int(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT = 1;"
            );
            self::$pdo->exec(
                "DROP TABLE IF EXISTS {$GLOBALS[self::DB_HYBRID_TABLE]};" .
                "CREATE TABLE {$GLOBALS[self::DB_HYBRID_TABLE]} (" .
                "id int(4) NOT NULL," .
                "lft int(4) NOT NULL," .
                "rgt int(4) NOT NULL," .
                "parent int(4) NOT NULL," .
                "ordering int(4) NOT NULL," .
                "depth int(4) NOT NULL," .
                "value varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT ''" .
                ") ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;" .
                "ALTER TABLE {$GLOBALS[self::DB_HYBRID_TABLE]} ADD PRIMARY KEY(id);" .
                "ALTER TABLE {$GLOBALS[self::DB_HYBRID_TABLE]} " .
                "MODIFY id int(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT = 1;"
            );
            self::$pdo->commit();
        } catch (\PDOException $exception) {
            self::$pdo->rollBack();
            throw new $exception;
        }
    }

    public static function tearDownAfterClass()
    {
        self::$pdo->exec("DROP TABLE IF EXISTS {$GLOBALS[self::DB_TABLE]};");
        self::$pdo->exec("DROP TABLE IF EXISTS {$GLOBALS[self::DB_HYBRID_TABLE]};");
        self::$pdo = null;
    }

    /**
     * @param string $table
     * @return ITable
     */
    protected function getQueryTable($table)
    {
        /** @var AbstractTest $this */

        return $this->getConnection()->createQueryTable(
            $table,
            "SELECT * FROM {$table}"
        );
    }

    /**
     * @param string $table Table name
     * @param string $fixture Path to fixture
     * @return void
     */
    protected function assertTableAndFixtureEqual($table, $fixture)
    {
        $fixture = $this->createMySQLXMLDataSet($fixture);
        $data = $this->getQueryTable($table);

        $this->assertTablesEqual(
            $fixture->getTable($table),
            $data
        );
    }

    /**
     * @param ResultInterface $result  Result set to be matched against
     * @param string          $table   Table name
     * @param string          $fixture Path to fixture
     * @return void
     */
    protected function assertResultAndFixtureEqual($result, $table, $fixture)
    {
        $fixture = $this->createMySQLXMLDataSet($fixture);
        $result = $this->createArrayDataSet([$table => ArrayUtils::iteratorToArray($result)]);

        $this->assertTablesEqual(
            $fixture->getTable($table),
            $result->getTable($table)
        );
    }
}