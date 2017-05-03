<?php

namespace metalinspired\NestedSetTest;

use metalinspired\NestedSet\NestedSet;

class MoveTest
    extends AbstractNestedSetTest
{
    public function getDataSet()
    {
        return $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/Insert.xml');
    }

    public function testMoveNodeBehind()
    {
        $rows = self::$nestedSet->move(3, 14);

        $this->assertTablesEqual(
            $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/MoveNodeBehind.xml')->getTable($GLOBALS[self::DB_TABLE]),
            $this->getQueryTable()
        );

        $this->assertEquals( 12, $rows);
    }

    public function testMoveNodeBefore()
    {

        $rows = self::$nestedSet->move(20, 4, NestedSet::MOVE_BEFORE);

        $this->assertTablesEqual(
            $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/MoveNodeBefore.xml')->getTable($GLOBALS[self::DB_TABLE]),
            $this->getQueryTable()
        );

        $this->assertEquals( 1, $rows);
    }

    public function testMoveNodeChild()
    {

        $rows = self::$nestedSet->move(20, 9, NestedSet::MOVE_CHILD);

        $this->assertTablesEqual(
            $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/MoveNodeChild.xml')->getTable($GLOBALS[self::DB_TABLE]),
            $this->getQueryTable()
        );

        $this->assertEquals( 1, $rows);
    }
}