<?php

namespace metalinspired\NestedSetTest;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Exception\NoTableSetException;
use metalinspired\NestedSet\Exception\RuntimeException;
use metalinspired\NestedSet\Find\FindSiblings;
use Zend\Stdlib\ArrayUtils;

class FindSiblingsTest extends AbstractFindTest
{
    /**
     * @var FindSiblings
     */
    protected $findSiblings;

    public function setUp()
    {
        parent::setUp();

        $config = Config::createWithPdo(self::$pdo);
        $config->setTable($GLOBALS[self::DB_TABLE]);
        $this->findSiblings = new FindSiblings($config);
    }

    public function testUseObjectAsFunction()
    {
        $result = ($this->findSiblings)(23);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindSiblings.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindSiblings()
    {
        $this->assertNotTrue($this->findSiblings->isCached());

        $result = $this->findSiblings->find(23);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindSiblings.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );

        $this->assertTrue($this->findSiblings->isCached());
    }

    public function testFindSiblingsWithColumnsSpecified()
    {
        $result = $this->findSiblings
            ->setColumns(self::$customColumns)
            ->find(23);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindSiblingsWithColumnsSpecified.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindSiblingsWithSearchingNodeIncluded()
    {
        $result = $this->findSiblings
            ->setIncludeSearchingNode(true)
            ->find(23);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindSiblingsWithSearchingNodeIncluded.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    /*public function testFindSiblingsWithNoTableSet()
    {
        $this->expectException(NoTableSetException::class);

        $this->findSiblings->find(23);
    }*/
}