<?php

namespace metalinspired\NestedSetTest;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Exception\RuntimeException;
use metalinspired\NestedSet\Manipulate;

class ManipulateTest extends AbstractTest
{
    use GetDataSetTrait, GetQueryTableTrait;

    /**
     * @var Manipulate
     */
    protected $manipulate;

    public function setUp()
    {
        parent::setUp();

        $config = Config::createWithPdo(self::$pdo);
        $config->table = $GLOBALS[self::DB_TABLE];
        $config->rootNodeId = 1;
        $this->manipulate = new Manipulate($config);
    }

    public function testMoveNodeAfter()
    {
        $rows = $this->manipulate->moveAfter(3, 14);

        $this->assertEquals(14, $rows);

        $queryTables = $this->getQueryTables();
        $dataSet = $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/MoveNodeAfter.xml');

        $this->assertTablesEqual(
            $dataSet->getTable($GLOBALS[self::DB_TABLE]),
            $queryTables[self::DB_TABLE]
        );

        $rows = $this->manipulate->moveAfter(3, 2);

        $this->assertEquals(14, $rows);

        $queryTables = $this->getQueryTables();
        $dataSet = $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/Insert.xml');

        $this->assertTablesEqual(
            $dataSet->getTable($GLOBALS[self::DB_TABLE]),
            $queryTables[self::DB_TABLE]
        );
    }

    public function testMoveNodeAfterRootNode()
    {
        $this->expectException(RuntimeException::class);

        $this->manipulate->moveAfter(20, 1);
    }

    public function testMoveNodeBefore()
    {

        $rows = $this->manipulate->moveBefore(20, 4);

        $this->assertEquals(18, $rows);

        $queryTables = $this->getQueryTables();
        $dataSet = $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/MoveNodeBefore.xml');

        $this->assertTablesEqual(
            $dataSet->getTable($GLOBALS[self::DB_TABLE]),
            $queryTables[self::DB_TABLE]
        );
    }

    public function testMoveNodeBeforeRootNode()
    {
        $this->expectException(RuntimeException::class);

        $this->manipulate->moveBefore(20, 1);
    }

    public function testMoveNodeMakeChild()
    {

        $rows = $this->manipulate->moveMakeChild(20, 9);

        $this->assertEquals(2, $rows);

        $queryTables = $this->getQueryTables();
        $dataSet = $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/MoveNodeChild.xml');

        $this->assertTablesEqual(
            $dataSet->getTable($GLOBALS[self::DB_TABLE]),
            $queryTables[self::DB_TABLE]
        );
    }

    public function testMoveRootNode()
    {
        $this->expectException(RuntimeException::class);

        $this->manipulate->move(1, 20);
    }

    public function testDelete()
    {
        $rows = $this->manipulate->delete(3);

        $this->assertEquals(12, $rows);

        $queryTables = $this->getQueryTables();
        $dataSet = $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/Delete.xml');

        $this->assertTablesEqual(
            $dataSet->getTable($GLOBALS[self::DB_TABLE]),
            $queryTables[self::DB_TABLE]
        );
    }

    public function testDeleteRootNode()
    {
        $this->expectException(RuntimeException::class);

        $this->manipulate->delete(1);
    }

    public function testDeleteNonExistingNode()
    {
        $this->expectException(RuntimeException::class);

        $this->manipulate->delete(100);
    }

    public function testClean()
    {
        $rows = $this->manipulate->clean(3);

        $this->assertEquals(13, $rows);

        $queryTables = $this->getQueryTables();
        $dataSet = $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/Clean.xml');

        $this->assertTablesEqual(
            $dataSet->getTable($GLOBALS[self::DB_TABLE]),
            $queryTables[self::DB_TABLE]
        );
    }

    public function testCleanWithMoving()
    {
        $rows = $this->manipulate->clean(3, 4);

        $this->assertEquals(13, $rows);

        $queryTables = $this->getQueryTables();
        $dataSet = $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/CleanWithMoving.xml');

        $this->assertTablesEqual(
            $dataSet->getTable($GLOBALS[self::DB_TABLE]),
            $queryTables[self::DB_TABLE]
        );
    }
}
