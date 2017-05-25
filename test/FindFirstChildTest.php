<?php

namespace metalinspired\NestedSetTest;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Exception\NoTableSetException;
use metalinspired\NestedSet\Find\FindFirstChild;
use Zend\Stdlib\ArrayUtils;

class FindFirstChildTest extends AbstractFindTest
{
    /**
     * @var FindFirstChild
     */
    protected $findFirstChild;

    public function setUp()
    {
        parent::setUp();

        $config = Config::createWithPdo(self::$pdo);
        $config->setTable($GLOBALS[self::DB_TABLE]);
        $this->findFirstChild = new FindFirstChild($config);
    }

    public function testUseObjectAsFunction()
    {
        $result = ($this->findFirstChild)(3);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindFirstChild.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindFirstChild()
    {
        $this->assertNotTrue($this->findFirstChild->isCached());

        $result = $this->findFirstChild->find(3);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindFirstChild.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );

        $this->assertTrue($this->findFirstChild->isCached());
    }

    public function testFindFirstChildWithColumnsSpecified()
    {
        $result = $this->findFirstChild
            ->setColumns(self::$customColumns)
            ->find(3);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindFirstChildWithColumnsSpecified.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindFirstChildWithSearchingNodeIncluded()
    {
        $result = $this->findFirstChild
            ->setIncludeSearchingNode(true)
            ->find(3);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindFirstChildWithSearchingNodeIncluded.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindFirstChildOfRootNodeWithSearchingNodeIncluded()
    {
        $result = $this->findFirstChild
            ->setIncludeSearchingNode(true)
            ->find(1);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindFirstChildOfRootNodeWithSearchingNodeIncluded.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    /*public function testFindFirstChildWithNoTableSet()
    {
        $this->expectException(NoTableSetException::class);

        $this->findFirstChild->find(3);
    }*/
}