<?php

namespace metalinspired\NestedSetTest\NestedSet;

use metalinspired\NestedSet\Exception\RuntimeException;

class CreateRootTest extends AbstractManipulateTest
{
    public function getDataSet()
    {
        return $this->createXMLDataSet(__DIR__ . '/Fixture/InitialState.xml');
    }

    public function testCreateRoot()
    {
        $rootId = self::$manipulate->createRootNode();

        $this->assertEquals(1, $rootId);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/CreateRoot.xml'
        );

        /*
         * Try to create root element when table is no longer empty
         */
        $this->expectException(RuntimeException::class);

        self::$manipulate->createRootNode();
    }
}
