<?php

namespace metalinspired\NestedSetTest;

class CreateRootTest
    extends AbstractTest
{
    public function getDataSet()
    {
        return $this->createXMLDataSet(__DIR__ . '/Fixture/InitialState.xml');
    }

    public function testCreateRoot()
    {
        $rootId = self::$nestedSet->createRootNode();

        $this->assertEquals(1, $rootId);

        $this->assertTablesEqual(
            $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/CreateRoot.xml')->getTable($GLOBALS[self::DB_TABLE]),
            $this->getQueryTable()
        );
    }
}