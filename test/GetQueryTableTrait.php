<?php

namespace metalinspired\NestedSetTest;

use PHPUnit\DbUnit\TestCase;

trait GetQueryTableTrait
{
    protected function getQueryTable()
    {
        /** @var AbstractTest $this */
        return $this->getConnection()->createQueryTable(
            $GLOBALS[self::DB_TABLE],
            'SELECT * FROM `' . $GLOBALS[self::DB_TABLE] . '`;'
        );
    }
}