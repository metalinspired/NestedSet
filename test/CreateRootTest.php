<?php

namespace metalinspired\NestedSetTest;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Exception\RuntimeException;
use metalinspired\NestedSet\Manipulate\CreateRoot;

class CreateRootTest extends AbstractManipulateTest
{
    /**
     * @var CreateRoot
     */
    protected $createRoot;

    public function setUp()
    {
        parent::setUp();

        $config = Config::createWithPdo(self::$pdo);
        $config->setTable($GLOBALS[self::DB_TABLE]);
        $this->createRoot = new CreateRoot($config);
    }

    public function getDataSet()
    {
        return $this->createXMLDataSet(__DIR__ . '/Fixture/InitialState.xml');
    }

    public function testUseObjectAsFunction()
    {
        ($this->createRoot)();

        $this->assertTablesEqual(
            $this
                ->createMySQLXMLDataSet(__DIR__ . '/Fixture/CreateRoot.xml')
                ->getTable($GLOBALS[self::DB_TABLE]),
            $this->getQueryTable()
        );
    }

    public function testCreateRoot()
    {
        $rootId = $this->createRoot->create();

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

        $this->createRoot->create();
    }
}
