<?php

namespace metalinspired\NestedSetTest;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Exception\NoTableSetException;
use metalinspired\NestedSet\Find\FindNextSibling;
use Zend\Stdlib\ArrayUtils;

class FindNextSiblingTest extends AbstractFindTest
{
    /**
     * @var FindNextSibling
     */
    protected $findNextSibling;

    public function setUp()
    {
        parent::setUp();

        $config = Config::createWithPdo(self::$pdo);
        $config->setTable($GLOBALS[self::DB_TABLE]);
        $this->findNextSibling = new FindNextSibling($config);
    }

    public function testUseObjectAsFunction()
    {
        $result = ($this->findNextSibling)(33);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindNextSibling.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindNextSibling()
    {
        $this->assertNotTrue($this->findNextSibling->isCached());

        $result = $this->findNextSibling->find(33);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindNextSibling.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );

        $this->assertTrue($this->findNextSibling->isCached());
    }

    public function testFindNextSiblingOfLastNode()
    {
        $this->assertNotTrue($this->findNextSibling->isCached());

        $result = $this->findNextSibling->find(36);

        $this->assertTablesEqual(
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => []])
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );

        $this->assertTrue($this->findNextSibling->isCached());
    }

    public function testFindNextSiblingWithColumnsSpecified()
    {
        $result = $this->findNextSibling
            ->setColumns(self::$customColumns)
            ->find(33);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindNextSiblingWithColumnsSpecified.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindNextSiblingWithSearchingNodeIncluded()
    {
        $result = $this->findNextSibling
            ->setIncludeSearchingNode(true)
            ->find(33);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindNextSiblingWithSearchingNodeIncluded.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindNextSiblingOfLastNodeWithSearchingNodeIncluded()
    {
        $result = $this->findNextSibling
            ->setIncludeSearchingNode(true)
            ->find(36);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindNextSiblingOfLastNodeWithSearchingNodeIncluded.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindNextSiblingOfRootNodeWithSearchingNodeIncluded()
    {
        $result = $this->findNextSibling
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

    /*public function testFindNextSiblingWithNoTableSet()
    {
        $this->expectException(NoTableSetException::class);

        $this->findNextSibling->find(33);
    }*/
}