<?php

namespace metalinspired\NestedSetTest;

use metalinspired\NestedSet\NestedSetSelect;

class SelectTest
    extends AbstractTest
{
    /**
     * @var NestedSetSelect
     */
    static protected $nestedSetSelect = null;

    public function getDataSet()
    {
        return $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/Insert.xml');
    }

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$nestedSetSelect = new NestedSetSelect(self::$pdo, $GLOBALS[self::DB_TABLE]);
    }

    public function testFindChildren()
    {
        $result = self::$nestedSetSelect->findChildren(3);

        $this->assertTablesEqual(
            $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindChildren.xml')->getTable($GLOBALS[self::DB_TABLE]),
            $this->createArrayDataSet([$GLOBALS[self::DB_TABLE] => $result])->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindDescendants()
    {
        $result = self::$nestedSetSelect->findDescendants(3);

        $this->assertTablesEqual(
            $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindDescendants.xml')->getTable($GLOBALS[self::DB_TABLE]),
            $this->createArrayDataSet([$GLOBALS[self::DB_TABLE] => $result])->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindParent()
    {
        $result = self::$nestedSetSelect->findParent(33);

        $this->assertTablesEqual(
            $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindParent.xml')->getTable($GLOBALS[self::DB_TABLE]),
            $this->createArrayDataSet([$GLOBALS[self::DB_TABLE] => [$result]])->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindAncestors()
    {
        $result = self::$nestedSetSelect->findAncestors(33);

        $this->assertTablesEqual(
            $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindAncestors.xml')->getTable($GLOBALS[self::DB_TABLE]),
            $this->createArrayDataSet([$GLOBALS[self::DB_TABLE] => $result])->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindSiblings()
    {
        $result = self::$nestedSetSelect->findSiblings(23);

        $this->assertTablesEqual(
            $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindSiblings.xml')->getTable($GLOBALS[self::DB_TABLE]),
            $this->createArrayDataSet([$GLOBALS[self::DB_TABLE] => $result])->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindPrevSibling()
    {
        $result = self::$nestedSetSelect->findPrevSibling(33);

        $this->assertTablesEqual(
            $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindPrevSibling.xml')->getTable($GLOBALS[self::DB_TABLE]),
            $this->createArrayDataSet([$GLOBALS[self::DB_TABLE] => [$result]])->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindNextSibling()
    {
        $result = self::$nestedSetSelect->findNextSibling(33);

        $this->assertTablesEqual(
            $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindNextSibling.xml')->getTable($GLOBALS[self::DB_TABLE]),
            $this->createArrayDataSet([$GLOBALS[self::DB_TABLE] => [$result]])->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindFirstChild()
    {
        $result = self::$nestedSetSelect->findFirstChild(3);

        $this->assertTablesEqual(
            $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindFirstChild.xml')->getTable($GLOBALS[self::DB_TABLE]),
            $this->createArrayDataSet([$GLOBALS[self::DB_TABLE] => [$result]])->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindLastChild()
    {
        $result = self::$nestedSetSelect->findLastChild(3);

        $this->assertTablesEqual(
            $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindLastChild.xml')->getTable($GLOBALS[self::DB_TABLE]),
            $this->createArrayDataSet([$GLOBALS[self::DB_TABLE] => [$result]])->getTable($GLOBALS[self::DB_TABLE])
        );
    }
}