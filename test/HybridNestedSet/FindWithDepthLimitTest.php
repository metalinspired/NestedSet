<?php

namespace metalinspired\NestedSetTest\HybridNestedSet;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\HybridFind;

class FindWithDepthLimitTest extends AbstractFindTest
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
        $config->depthLimit = 2;
        $config->rootNodeId = 1;

        $this->find = new HybridFind($config);
    }

    public function testFindDescendants()
    {
        $result = $this->find->findDescendants(1);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_HYBRID_TABLE],
            __DIR__ . '/Fixture/FindDescendantsWithDepthLimit.xml'
        );
    }
}
