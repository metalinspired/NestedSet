<?php

namespace metalinspired\NestedSetTest;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Exception\NoTableSetException;
use metalinspired\NestedSet\Find\FindDescendants;
use Zend\Stdlib\ArrayUtils;

class FindDescendantsTest extends AbstractFindTest
{
    /**
     * @var FindDescendants
     */
    protected $findDescendants;

    public function setUp()
    {
        parent::setUp();

        $config = Config::createWithPdo(self::$pdo);
        $config->setTable($GLOBALS[self::DB_TABLE]);
        $this->findDescendants = new FindDescendants($config);
    }

    public function testUseObjectAsFunction()
    {
        $result = ($this->findDescendants)(3);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindDescendants.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindDescendants()
    {
        $this->assertNotTrue($this->findDescendants->isCached());

        $result = $this->findDescendants->find(3);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindDescendants.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );

        $this->assertTrue($this->findDescendants->isCached());
    }

    public function testFindDescendantsWithLimit()
    {
        $result = $this->findDescendants
            ->setDepthLimit(1)
            ->find(3);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindChildren.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindDescendantsWithColumnsSpecified()
    {
        $result = $this->findDescendants
            ->setColumns(self::$customColumns)
            ->find(3);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindDescendantsWithColumnsSpecified.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindDescendantsWithSearchingNodeIncluded()
    {
        $result = $this->findDescendants
            ->setIncludeSearchingNode(true)
            ->find(3);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindDescendantsWithSearchingNodeIncluded.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    /*public function testFindDescendantsWithNoTableSet()
    {
        $this->expectException(NoTableSetException::class);

        $this->findDescendants->find(3);
    }*/
}