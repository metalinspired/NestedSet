<?php

namespace metalinspired\NestedSetTest;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Exception\RuntimeException;
use metalinspired\NestedSet\Find\FindChildren;
use Zend\Stdlib\ArrayUtils;

class FindChildrenTest extends AbstractFindTest
{
    /**
     * @var FindChildren
     */
    protected $findChildren;

    public function setUp()
    {
        parent::setUp();
        $config = Config::createWithPdo(self::$pdo);
        $config->setTable($GLOBALS[self::DB_TABLE]);
        $this->findChildren = new FindChildren($config);
    }

    public function testFindChildren()
    {
        $result = $this->findChildren->find(3);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindChildren.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindChildrenSetDepthLimit()
    {
        $this->expectException(RuntimeException::class);

        $this->findChildren->setDepthLimit(2);
    }
}