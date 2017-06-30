<?php

namespace metalinspired\NestedSetTest\NestedSet;

use metalinspired\NestedSetTest\AbstractTest;

abstract class AbstractFindTest extends AbstractTest
{
    public function getDataSet()
    {
        return $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/Insert.xml');
    }

    protected static $customColumns = ['new_lft' => 'lft', 'rgt', 'text' => 'value'];
}