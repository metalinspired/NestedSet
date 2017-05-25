<?php

namespace metalinspired\NestedSetTest;

abstract class AbstractManipulateTest extends AbstractTest
{
    protected function getQueryTable()
    {
        return $this->getConnection()->createQueryTable(
            $GLOBALS[self::DB_TABLE],
            'SELECT * FROM `' . $GLOBALS[self::DB_TABLE] . '`;'
        );
    }
}