<?php

namespace metalinspired\NestedSetTest;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Manipulate\Move;
use metalinspired\NestedSet\NestedSet;

class MoveTest extends AbstractManipulateTest
{
    /**
     * @var Move
     */
    protected $move;

    public function setUp()
    {
        parent::setUp();

        $config = Config::createWithPdo(self::$pdo);
        $config->setTable($GLOBALS[self::DB_TABLE]);
        $this->move = new Move($config);
    }

    public function getDataSet()
    {
        return $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/Insert.xml');
    }

    public function testUseObjectAsFunction()
    {
        $rows = ($this->move)(3, 14, Move::MOVE_AFTER);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/MoveNodeBehind.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this->getQueryTable()
        );

        $this->assertEquals(12, $rows);
    }

    public function testMoveNodeAfter()
    {
        $rows = $this->move->after(3, 14);

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

        $rows = $this->move->before(20, 4);

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

        $rows = $this->move->makeChild(20, 9);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/MoveNodeChild.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this->getQueryTable()
        );

        $this->assertEquals(1, $rows);
    }
}