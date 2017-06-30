<?php

namespace metalinspired\NestedSetTest\NestedSet;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Find;

class FindWithSearchingNodeIncludedTest extends AbstractFindTest
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
        $config->includeSearchingNode = true;
        $config->rootNodeId = 1;

        $this->find = new Find($config);
    }

    public function testFindDescendants()
    {
        $result = $this->find->findDescendants(12);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindDescendantsWithSearchingNodeIncluded.xml'
        );
    }

    public function testFindChildren()
    {
        $result = $this->find->findChildren(12);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindChildrenWithSearchingNodeIncluded.xml'
        );
    }

    public function testFindAncestors()
    {
        $result = $this->find->findAncestors(38);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindAncestorsWithSearchingNodeIncluded.xml'
        );
    }

    public function testFindParent()
    {
        $result = $this->find->findParent(38);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindParentWithSearchingNodeIncluded.xml'
        );
    }

    public function testFindFirstChild()
    {
        $result = $this->find->findFirstChild(22);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindFirstChildWithSearchingNodeIncluded.xml'
        );
    }

    public function testFindLastChild()
    {
        $result = $this->find->findLastChild(22);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindLastChildWithSearchingNodeIncluded.xml'
        );
    }

    public function testFindSiblings()
    {
        $result = $this->find->findSiblings(12);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindSiblingsWithSearchingNodeIncluded.xml'
        );
    }

    public function testFindNextSibling()
    {
        $result = $this->find->findNextSibling(22);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindNextSiblingWithSearchingNodeIncluded.xml'
        );
    }

    public function testFindPreviousSibling()
    {
        $result = $this->find->findPreviousSibling(22);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindPreviousSiblingWithSearchingNodeIncluded.xml'
        );
    }
}
