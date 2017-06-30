<?php

namespace metalinspired\NestedSetTest\HybridNestedSet;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\HybridFind;

class FindTest extends AbstractFindTest
{
    /**
     * @var HybridFind
     */
    protected $find;

    public function setUp()
    {
        parent::setUp();

        $config = Config::createWithPdo(self::$pdo);
        $config->table = $GLOBALS[self::DB_HYBRID_TABLE];
        $config->rootNodeId = 1;

        $this->find = new HybridFind($config);
    }

    public function testFindDescendants()
    {
        $result = $this->find->findDescendants(12);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_HYBRID_TABLE],
            __DIR__ . '/Fixture/FindDescendants.xml'
        );
    }

    public function testFindChildren()
    {
        $result = $this->find->findChildren(12);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_HYBRID_TABLE],
            __DIR__ . '/Fixture/FindChildren.xml'
        );
    }

    public function testFindParent()
    {
        $result = $this->find->findParent(38);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_HYBRID_TABLE],
            __DIR__ . '/Fixture/FindParent.xml'
        );
    }

    public function testFindSiblings()
    {
        $result = $this->find->findSiblings(12);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_HYBRID_TABLE],
            __DIR__ . '/Fixture/FindSiblings.xml'
        );
    }
}
