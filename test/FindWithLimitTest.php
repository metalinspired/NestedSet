<?php

namespace metalinspired\NestedSetTest;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Find;
use Zend\Stdlib\ArrayUtils;

class FindWithLimitTest extends AbstractFindTest
{
    /**
     * @var Find
     */
    protected $nestedSet;

    public function setUp()
    {
        parent::setUp();

        $config = Config::createWithPdo(self::$pdo);
        $config->table = $GLOBALS[self::DB_TABLE];
        $config->depthLimit = 2;
        $config->rootNodeId = 1;

        $this->nestedSet = new Find($config);
    }

    public function testFindDescendantsWithLimit()
    {
        $result = $this->nestedSet->findDescendants(1);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindDescendantsWithLimit.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }

    public function testFindAncestorsWithLimit()
    {
        // TODO: add deeper nodes to table so that this test differs from regular find ancestors
        $result = $this->nestedSet->findAncestors(33);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/FindAncestors.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this
                ->createArrayDataSet([$GLOBALS[self::DB_TABLE] => ArrayUtils::iteratorToArray($result)])
                ->getTable($GLOBALS[self::DB_TABLE])
        );
    }
}
