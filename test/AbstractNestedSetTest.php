<?php

namespace metalinspired\NestedSetTest;

use metalinspired\NestedSet\NestedSet;

abstract class AbstractNestedSetTest
    extends AbstractTest
{
    /**
     * @var NestedSet
     */
    static protected $nestedSet = null;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        // create NestedSet object
        self::$nestedSet = new NestedSet(self::$pdo, $GLOBALS[self::DB_TABLE]);
    }

    protected function getQueryTable()
    {
        return $this->getConnection()->createQueryTable(
            $GLOBALS[self::DB_TABLE],
            'SELECT * FROM `' . $GLOBALS[self::DB_TABLE] . '`;'
        );
    }
}