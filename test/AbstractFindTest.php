<?php

namespace metalinspired\NestedSetTest;

abstract class AbstractFindTest extends AbstractTest
{
    protected static $customColumns = ['new_lft' => 'lft', 'rgt', 'text' => 'value'];

    public function getDataSet()
    {
        return $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/Insert.xml');
    }
}