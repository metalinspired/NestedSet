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

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/MoveNodeBehind.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this->getQueryTable()
        );

        $this->assertEquals(12, $rows);
    }

    public function testMoveNodeBefore()
    {

        $rows = $this->manipulate->moveBefore(20, 4);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/MoveNodeBefore.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this->getQueryTable()
        );

        $this->assertEquals(1, $rows);
    }

    public function testMoveNodeMakeChild()
    {

        $rows = $this->manipulate->moveMakeChild(20, 9);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/MoveNodeChild.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this->getQueryTable()
        );

        $this->assertEquals(1, $rows);
    }

    public function testDelete()
    {
        $rows = $this->manipulate->delete(3);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/Delete.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this->getQueryTable()
        );

        $this->assertEquals( 12, $rows);
    }

    public function testDeleteNonExistingNode()
    {
        $this->expectException(RuntimeException::class);

        $this->manipulate->delete(100);
    }
}
