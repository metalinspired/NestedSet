<?php

namespace metalinspired\NestedSetTest;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Exception\RuntimeException;
use metalinspired\NestedSet\Find\FindParent;
use Zend\Stdlib\ArrayUtils;

class FindParentTest extends AbstractFindTest
{
    /**
     * @var FindParent
     */
    protected $findParent;

    public function setUp()
    {
        parent::setUp();

        $config = Config::createWithPdo(self::$pdo);
        $config->setTable($GLOBALS[self::DB_TABLE]);
        $this->findParent = new FindParent($config);
    }

    public function testFindParent()
    {
        $result = $this->findParent->find(33);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindParent.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindParentWithSearchingNodeIncluded()
    {
        $result = $this->findParent
            ->setIncludeSearchingNode(true)
            ->find(33);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindParentWithSearchingNodeIncluded.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindParentSetDepthLimit()
    {
        $this->expectException(RuntimeException::class);

        $this->findParent->setDepthLimit(2);
    }
}