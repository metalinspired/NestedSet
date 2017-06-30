<?php

namespace metalinspired\NestedSetTest\NestedSet;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Exception\RootNodeNotDetectedException;
use metalinspired\NestedSet\Find;
use metalinspired\NestedSetTest\AbstractTest;

class InvalidRootNodeTest extends AbstractTest
{
    /**
     * @var Find
     */
    protected $find;

    public function setUp()
    {
        parent::setUp();

        $config = Config::createWithPdo(self::$pdo);
        $config->table = $GLOBALS[self::DB_TABLE];
        $this->find = new Find($config);
    }

    public function getDataSet()
    {
        return $this->createXMLDataSet(__DIR__ . '/Fixture/InitialState.xml');
    }

    public function testEmptyTable()
    {
        $this->expectException(RootNodeNotDetectedException::class);

        $this->find->findChildren(1);
    }

    public function testNoRootNode()
    {
        self::$pdo->exec("INSERT INTO `{$GLOBALS[self::DB_TABLE]}` (`lft`, `rgt`, `value`) VALUES (1, 2, 'node 1')");
        self::$pdo->exec("INSERT INTO `{$GLOBALS[self::DB_TABLE]}` (`lft`, `rgt`, `value`) VALUES (3, 4, 'node 2')");

        $this->expectException(RootNodeNotDetectedException::class);

        $this->find->findChildren(1);
    }
}
