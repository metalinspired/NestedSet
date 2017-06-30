<?php

namespace metalinspired\NestedSetTest\NestedSet;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Find;

class FindWithDepthLimitTest extends AbstractFindTest
{
    /**
     * @var Find
     */
    protected $find;

    public function setUp()
    {
        parent::setUp();

        $config = Config::createWithPdo(self::$pdo);
        $config->table = $GLOBALS[self::DB_TABLE];
        $config->depthLimit = 2;
        $config->rootNodeId = 1;

        $this->find = new Find($config);
    }

    public function testFindDescendants()
    {
        $result = $this->find->findDescendants(1);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindDescendantsWithDepthLimit.xml'
        );
    }

    public function testFindAncestors()
    {
        $result = $this->find->findAncestors(38);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindAncestorsWithDepthLimit.xml'
        );
    }
}
