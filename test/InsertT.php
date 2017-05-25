<?php

namespace metalinspired\NestedSetTest;

class InsertT
    extends AbstractManipulateTest
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

        // Add five more children to Node 1
        for ($i = 1; $i < 6; $i++) {
            self::$nestedSet->insert(['value' => 'Extra sub ' . $i], 3);
        }

        // Add five more children to Node 4
        for ($i = 1; $i < 6; $i++) {
            self::$nestedSet->insert(['value' => 'Extra sub ' . $i], 6);
        }

        // Add five children to Extra node 2 (Node 1)
        for ($i=1; $i<6; $i++) {
            self::$nestedSet->insert(['value' => 'Extra extra sub ' . $i], 23);
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