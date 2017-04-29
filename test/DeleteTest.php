<?php

namespace metalinspired\NestedSetTest;

class DeleteTest
    extends AbstractTest
{
    public function getDataSet()
    {
        return $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/Insert.xml');
    }

    public function testDelete()
    {
        $rows = self::$nestedSet->delete(6);

        $this->assertTablesEqual(
            $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/Delete.xml')->getTable($GLOBALS[self::DB_TABLE]),
            $this->getQueryTable()
        );

        $this->assertEquals( 2, $rows);
    }

    /**
     * @expectedException \metalinspired\NestedSet\Exception\RuntimeException
     */
    public function testDeleteNonExistingNode()
    {
        self::$nestedSet->delete(100);
    }
}