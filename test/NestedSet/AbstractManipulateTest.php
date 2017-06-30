<?php

namespace metalinspired\NestedSetTest\NestedSet;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Manipulate;
use metalinspired\NestedSetTest\AbstractTest;

abstract class AbstractManipulateTest extends AbstractTest
{
    /**
     * @var Manipulate
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
        $config->table = $GLOBALS[self::DB_TABLE];
        $config->rootNodeId = 1;
        self::$manipulate = new Manipulate($config);
    }
}