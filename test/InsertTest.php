<?php

namespace metalinspired\NestedSetTest;

class InsertTest
    extends AbstractTest
{
    public function getDataSet()
    {
        return $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/CreateRoot.xml');
    }

    public function testInsertNodes()
    {
        // Insert first level nodes
        for ($i = 0; $i < 10; $i++) {
            self::$nestedSet->insert(['value' => 'Node ' . $i], 1);
        }

        // Insert a second level node in every first level node
        for ($i = 2; $i < 12; $i++) {
            self::$nestedSet->insert(['value' => 'Sub node ' . ($i - 2)], $i);
        }

        $this->assertTablesEqual(
            $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/Insert.xml')->getTable($GLOBALS[self::DB_TABLE]),
            $this->getQueryTable()
        );
    }

    /**
     * @expectedException \metalinspired\NestedSet\Exception\InvalidArgumentException
     */
    public function testCreateNodeWithStringAsParent()
    {
        self::$nestedSet->insert([], "one");
    }

    /**
     * @expectedException \metalinspired\NestedSet\Exception\RuntimeException
     */
    public function testCreateNodeInNonExistingParent()
    {
        self::$nestedSet->insert([], 10);
    }
}