<?php

namespace metalinspired\NestedSetTest;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Exception\NoTableSetException;
use metalinspired\NestedSet\Find\FindAncestors;
use Zend\Stdlib\ArrayUtils;

class FindAncestorsTest extends AbstractFindTest
{
    /**
     * @var FindAncestors
     */
    protected $findAncestors;

    public function setUp()
    {
        parent::setUp();

        $config = Config::createWithPdo(self::$pdo);
        $config->setTable($GLOBALS[self::DB_TABLE]);
        $this->findAncestors = new FindAncestors($config);
    }

    public function testUseObjectAsFunction()
    {
        $result = ($this->findAncestors)(33);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindAncestors.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindAncestors()
    {
        $this->assertNotTrue($this->findAncestors->isCached());

        $result = $this->findAncestors->find(33);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindAncestors.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );

        $this->assertTrue($this->findAncestors->isCached());
    }

    public function testFindAncestorsWithLimit()
    {
        $result = $this->findAncestors
            ->setDepthLimit(1)
            ->find(33);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindParent.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindAncestorsWithColumnsSpecified()
    {
        $result = $this->findAncestors
            ->setColumns(self::$customColumns)
            ->find(33);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindAncestorsWithColumnsSpecified.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindAncestorsWithSearchingNodeIncluded()
    {
        $result = $this->findAncestors
            ->setIncludeSearchingNode(true)
            ->find(33);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindAncestorsWithSearchingNodeIncluded.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    /*public function testFindAncestorsWithNoTableSet()
    {
        $this->expectException(NoTableSetException::class);

        $this->findAncestors->find(33);
    }*/
}
