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
        $this->manipulate = new Manipulate($config);
    }

    public function testMoveNodeAfter()
    {
        $rows = $this->manipulate->moveAfter(3, 14);

        $this->assertEquals(12, $rows);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/MoveNodeBehind.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this->getQueryTable()
        );
    }

    public function testMoveNodeBefore()
    {

        $rows = $this->manipulate->moveBefore(20, 4);

        $this->assertEquals(1, $rows);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/MoveNodeBefore.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this->getQueryTable()
        );
    }

    public function testMoveNodeMakeChild()
    {

        $rows = $this->manipulate->moveMakeChild(20, 9);

        $this->assertEquals(1, $rows);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/MoveNodeChild.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this->getQueryTable()
        );
    }

    public function testDelete()
    {
        $rows = $this->manipulate->delete(3);

        $this->assertEquals( 12, $rows);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/Delete.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this->getQueryTable()
        );
    }

    public function testDeleteNonExistingNode()
    {
        $this->expectException(RuntimeException::class);

        $this->manipulate->delete(100);
    }

    public function testClean()
    {
        $rows = $this->manipulate->clean(3, 4);

        $this->assertEquals(11, $rows);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/Clean.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this->getQueryTable()
        );
    }
}
