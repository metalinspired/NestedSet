<?php

namespace metalinspired\NestedSetTest;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Exception\NoTableSetException;
use metalinspired\NestedSet\Find\FindLastChild;
use Zend\Stdlib\ArrayUtils;

class FindLastChildTest extends AbstractFindTest
{
    /**
     * @var FindLastChild
     */
    protected $findLastChild;

    public function setUp()
    {
        parent::setUp();

        $config = Config::createWithPdo(self::$pdo);
        $config->setTable($GLOBALS[self::DB_TABLE]);
        $this->findLastChild = new FindLastChild($config);
    }

    public function testUseObjectAsFunction()
    {
        $result = ($this->findLastChild)(3);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindLastChild.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindLastChild()
    {
        $this->assertNotTrue($this->findLastChild->isCached());

        $result = $this->findLastChild->find(3);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindLastChild.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );

        $this->assertTrue($this->findLastChild->isCached());
    }

    public function testFindLastChildWithColumnsSpecified()
    {
        $result = $this->findLastChild
            ->setColumns(self::$customColumns)
            ->find(3);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindLastChildWithColumnsSpecified.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindLastChildWithSearchingNodeIncluded()
    {
        $result = $this->findLastChild
            ->setIncludeSearchingNode(true)
            ->find(3);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindLastChildWithSearchingNodeIncluded.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindLastChildOfRootNodeWithSearchingNodeIncluded()
    {
        $result = $this->findLastChild
            ->setIncludeSearchingNode(true)
            ->find(1);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindLastChildOfRootNodeWithSearchingNodeIncluded.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    /*public function testFindLastChildWithNoTableSet()
    {
        $this->expectException(NoTableSetException::class);

        $this->findLastChild->find(3);
    }*/
}