<?php

namespace metalinspired\NestedSetTest;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Exception\RuntimeException;
use metalinspired\NestedSet\Manipulate;

class CreateRootTest extends AbstractTest
{
    use GetQueryTableTrait;
    /**
     * @var Manipulate
     */
    protected $manipulate;

    public function setUp()
    {
        parent::setUp();

        $config = Config::createWithPdo(self::$pdo);
        $config->table = $GLOBALS[self::DB_TABLE];
        $this->manipulate = new Manipulate($config);
    }

    public function getDataSet()
    {
        return $this->createXMLDataSet(__DIR__ . '/Fixture/InitialState.xml');
    }

    public function testCreateRoot()
    {
        $rootId = $this->manipulate->createRootNode();

        $this->assertEquals(1, $rootId);

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/CreateRoot.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this->getQueryTable()
        );

        /*
         * Now try to create root element when table is no longer empty
         */
        $this->expectException(RuntimeException::class);

        $this->manipulate->createRootNode();
    }
}
