<?php

namespace metalinspired\NestedSetTest;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Find;
use Zend\Stdlib\ArrayUtils;

class FindTest extends AbstractFindTest
{
    /**
     * @var Find
     */
    protected $nestedSet;

    public function setUp()
    {
        parent::setUp();

        $config = Config::createWithPdo(self::$pdo);
        $config->table = $GLOBALS[self::DB_TABLE];

        $this->nestedSet = new Find($config);
    }

    public function testFindDescendants()
    {
        $result = $this->nestedSet->findDescendants(3);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindDescendants.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindChildren()
    {
        $result = $this->nestedSet->findChildren(3);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindChildren.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindAncestors()
    {
        $result = $this->nestedSet->findAncestors(33);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindAncestors.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindParent()
    {
        $result = $this->nestedSet->findParent(33);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindParent.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindFirstChild()
    {
        $result = $this->nestedSet->findFirstChild(3);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindFirstChild.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindLastChild()
    {
        $result = $this->nestedSet->findLastChild(3);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindLastChild.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindSiblings()
    {
        $result = $this->nestedSet->findSiblings(23);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindSiblings.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindNextSibling()
    {
        $result = $this->nestedSet->findNextSibling(33);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindNextSibling.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindPreviousSibling()
    {
        $result = $this->nestedSet->findPreviousSibling(33);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindPreviousSibling.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }
}
