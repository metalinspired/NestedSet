<?php

namespace metalinspired\NestedSetTest;

use metalinspired\NestedSet\NestedSet;
use \PDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\DbUnit\Database\Connection;

abstract class AbstractTest
    extends TestCase
{
    use TestCaseTrait;

    const DB_DSN = 'DB_DSN',
        DB_USER = 'DB_USER',
        DB_PASSWORD = 'DB_PASSWORD',
        DB_NAME = 'DB_NAME',
        DB_TABLE = 'DB_TABLE';

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
     * Instance of NestedSet
     *
     * @var NestedSet
     */
    static protected $nestedSet = null;

    /**
     * Syntax to create table in SQLite database
     *
     * @var string
     */
    static protected $sqliteTable = 'CREATE TABLE [#tableName#] ' .
    '( ' .
    '[id] INTEGER PRIMARY KEY AUTOINCREMENT, ' .
    '[lft] INTEGER NOT NULL, ' .
    '[rgt] INTEGER NOT NULL, ' .
    '[value] TEXT DEFAULT \'\' NOT NULL ' .
    ');';

    /**
     * Syntax to create table in MySQL database
     *
     * @var string
     */
    static protected $mysqlTable = 'CREATE TABLE `#tableName#` (' .
    '`id` int(4) NOT NULL,' .
    '`lft` int(4) NOT NULL,' .
    '`rgt` int(4) NOT NULL,' .
    '`value` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT \'\'' .
    ') ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;' .
    'ALTER TABLE `#tableName#` ADD PRIMARY KEY(`id`);' .
    'ALTER TABLE `#tableName#` MODIFY `id` int(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT = 1;';

    final public function getConnection()
    {
        if (null === $this->conn) {
            $this->conn = $this->createDefaultDBConnection(self::$pdo, $GLOBALS[self::DB_NAME]);
        }

        return $this->conn;
    }

    final public static function setUpBeforeClass()
    {
        // create PDO object
        self::$pdo = new PDO($GLOBALS[self::DB_DSN], $GLOBALS[self::DB_USER], $GLOBALS[self::DB_PASSWORD]);

        // create NestedSet object
        self::$nestedSet = new NestedSet(self::$pdo, $GLOBALS[self::DB_TABLE]);

        switch (self::$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            case 'mysql':
                $table = self::$mysqlTable;
                break;
            case 'sqlite':
                $table = self::$sqliteTable;
                break;
            default:
                throw new \RuntimeException('Unsupported database type');
        }

        // create DB table
        try {
            self::$pdo->beginTransaction();
            self::$pdo->exec(
                'DROP TABLE IF EXISTS `' . $GLOBALS[self::DB_TABLE] . '`;' .
                str_replace('#tableName#', $GLOBALS[self::DB_TABLE], $table)
            );
            self::$pdo->commit();
        } catch (\PDOException $exception) {
            self::$pdo->rollBack();
            throw new $exception;
        }
    }

    final public static function tearDownAfterClass()
    {
        self::$pdo->exec('DROP TABLE IF EXISTS `' . $GLOBALS[self::DB_TABLE] . '`;');
        self::$pdo = null;
    }

    protected function getQueryTable()
    {
        return $this->getConnection()->createQueryTable(
            $GLOBALS[self::DB_TABLE],
            'SELECT * FROM `' . $GLOBALS[self::DB_TABLE] . '`;'
        );
    }
}