<?php

namespace metalinspired\NestedSetTest\NestedSet;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Find;

class FindWithColumnsSpecifiedTest extends AbstractFindTest
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
        $config->columns = self::$customColumns;
        $config->rootNodeId = 1;

        $this->find = new Find($config);
    }

    public function testFindDescendants()
    {
        $result = $this->find->findDescendants(12);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindDescendantsWithColumnsSpecified.xml'
        );
    }

    public function testFindChildren()
    {
        $result = $this->find->findChildren(12);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindChildrenWithColumnsSpecified.xml'
        );
    }

    public function testFindAncestors()
    {
        $result = $this->find->findAncestors(38);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindAncestorsWithColumnsSpecified.xml'
        );
    }

    public function testFindParent()
    {
        $result = $this->find->findParent(38);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindParentWithColumnsSpecified.xml'
        );
    }

    public function testFindFirstChild()
    {
        $result = $this->find->findFirstChild(22);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindFirstChildWithColumnsSpecified.xml'
        );
    }

    public function testFindLastChild()
    {
        $result = $this->find->findLastChild(22);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindLastChildWithColumnsSpecified.xml'
        );
    }

    public function testFindSiblings()
    {
        $result = $this->find->findSiblings(12);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindSiblingsWithColumnsSpecified.xml'
        );
    }

    public function testFindNextSibling()
    {
        $result = $this->find->findNextSibling(22);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindNextSiblingWithColumnsSpecified.xml'
        );
    }

    public function testFindPreviousSibling()
    {
        $result = $this->find->findPreviousSibling(22);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindPreviousSiblingWithColumnsSpecified.xml'
        );
    }
}
