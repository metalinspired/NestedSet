<?php

namespace metalinspired\NestedSetTest\HybridNestedSet;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\HybridManipulate;
use metalinspired\NestedSetTest\AbstractTest;

abstract class AbstractManipulateTest extends AbstractTest
{
    /**
     * @var HybridManipulate
     */
    protected static $manipulate;

    public function getDataSet()
    {
        return $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/Insert.xml');
    }

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        $config = Config::createWithPdo(self::$pdo);
        $config->table = $GLOBALS[self::DB_HYBRID_TABLE];
        $config->rootNodeId = 1;
        self::$manipulate = new HybridManipulate($config);
    }
}