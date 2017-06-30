<?php

namespace metalinspired\NestedSetTest\HybridNestedSet;

use metalinspired\NestedSet\Exception\RuntimeException;
use metalinspired\NestedSet\HybridManipulate;

class InsertTest extends AbstractManipulateTest
{

    public function getDataSet()
    {
        return $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/CreateRoot.xml');
    }

    /**
     * @param HybridManipulate $nestedSet
     * @return void
     */
    protected function createNodes($nestedSet)
    {
        $nodeCount = 1;
        for ($i = 0; $i < 4; $i++) {
            $iNode = $nestedSet->insert(1, ['value' => 'Node ' . $nodeCount++]);
            for ($j = 0; $j < 3; $j++) {
                $jNode = $nestedSet->insert($iNode, ['value' => 'Node ' . $nodeCount++]);
                for ($k = 0; $k < 2; $k++) {
                    $nestedSet->insert($jNode, ['value' => 'Node ' . $nodeCount++]);
                }
            }
        }
        for ($i = 0; $i < 3; $i++) {
            $nestedSet->insert(1, ['value' => 'Node ' . $nodeCount++]);
        }
    }

    /*
     * Nested Set
     */

    public function testInsertNodes()
    {
        $this->createNodes(self::$manipulate);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_HYBRID_TABLE],
            __DIR__ . '/Fixture/Insert.xml'
        );
    }

    public function testCreateNodeInNonExistingParent()
    {
        $this->expectException(RuntimeException::class);

        self::$manipulate->insert(10);
    }
}
