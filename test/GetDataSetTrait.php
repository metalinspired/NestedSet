<?php

namespace metalinspired\NestedSetTest;

trait GetDataSetTrait
{
    public function getDataSet()
    {
        /** @var AbstractTest $this */
        return $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/Insert.xml');
    }
}