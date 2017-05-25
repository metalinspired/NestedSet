<?php

namespace metalinspired\NestedSetTest;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Exception\NoTableSetException;
use metalinspired\NestedSet\Find\FindPreviousSibling;
use Zend\Stdlib\ArrayUtils;

class FindPreviousSiblingTest extends AbstractFindTest
{
    /**
     * @var FindPreviousSibling
     */
    protected $findPreviousSibling;

    public function setUp()
    {
        parent::setUp();

        $config = Config::createWithPdo(self::$pdo);
        $config->setTable($GLOBALS[self::DB_TABLE]);
        $this->findPreviousSibling = new FindPreviousSibling($config);
    }

    public function testUseObjectAsFunction()
    {
        $result = ($this->findPreviousSibling)(33);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindPreviousSibling.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindPreviousSibling()
    {
        $this->assertNotTrue($this->findPreviousSibling->isCached());

        $result = $this->findPreviousSibling->find(33);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindPreviousSibling.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );

        $this->assertTrue($this->findPreviousSibling->isCached());
    }

    public function testFindPreviousSiblingOfFirstNode()
    {
        $this->assertNotTrue($this->findPreviousSibling->isCached());

        $result = $this->findPreviousSibling->find(32);

        $this->assertTablesEqual(
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => []])
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );

        $this->assertTrue($this->findPreviousSibling->isCached());
    }

    public function testFindPreviousSiblingWithColumnsSpecified()
    {
        $result = $this->findPreviousSibling
            ->setColumns(self::$customColumns)
            ->find(33);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindPreviousSiblingWithColumnsSpecified.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindPreviousSiblingWithSearchingNodeIncluded()
    {
        $result = $this->findPreviousSibling
            ->setIncludeSearchingNode(true)
            ->find(33);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindPreviousSiblingWithSearchingNodeIncluded.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindPreviousSiblingOfFirstNodeWithSearchingNodeIncluded()
    {
        $result = $this->findPreviousSibling
            ->setIncludeSearchingNode(true)
            ->find(32);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindPreviousSiblingOfFirstNodeWithSearchingNodeIncluded.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindPreviousSiblingOfRootNodeWithSearchingNodeIncluded()
    {
        $result = $this->findPreviousSibling
            ->setIncludeSearchingNode(true)
            ->find(1);

        $this->assertTablesEqual(
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => []])
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    /*public function testFindPreviousSiblingWithNoTableSet()
    {
        $this->expectException(NoTableSetException::class);

        $this->findPreviousSibling->find(33);
    }*/
}