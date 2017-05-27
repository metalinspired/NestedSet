<?php

namespace metalinspired\NestedSetTest;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Exception\RuntimeException;
use metalinspired\NestedSet\Manipulate;

class InsertTest extends AbstractTest
{
    use GetQueryTableTrait;

    /**
     * @var Manipulate
     */
    protected $manipulate;
    
    public function getDataSet()
    {
        return $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/CreateRoot.xml');
    }
    
    public function setUp()
    {
        parent::setUp();
        
        $config = Config::createWithPdo(self::$pdo);
        $config->table = $GLOBALS[self::DB_TABLE];
        $this->manipulate = new Manipulate($config);
    }

    public function testInsertNodes()
    {
        // Insert first level nodes
        for ($i = 0; $i < 10; $i++) {
            $this->manipulate->insert(1, ['value' => 'Node ' . $i]);
        }

        // Insert a second level node in every first level node
        for ($i = 2; $i < 12; $i++) {
            $this->manipulate->insert($i, ['value' => 'Sub node ' . ($i - 2)]);
        }

        // Add five more children to Node 1
        for ($i = 1; $i < 6; $i++) {
            $this->manipulate->insert(3, ['value' => 'Extra sub ' . $i]);
        }

        // Add five more children to Node 4
        for ($i = 1; $i < 6; $i++) {
            $this->manipulate->insert(6, ['value' => 'Extra sub ' . $i]);
        }

        // Add five children to Extra node 2 (Node 1)
        for ($i=1; $i<6; $i++) {
            $this->manipulate->insert(23, ['value' => 'Extra extra sub ' . $i]);
        }

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/Insert.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this->getQueryTable()
        );
    }

    public function testCreateNodeInNonExistingParent()
    {
        $this->expectException(RuntimeException::class);

        $this->manipulate->insert(10);
    }
}
