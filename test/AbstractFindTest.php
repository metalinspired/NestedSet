<?php

namespace metalinspired\NestedSetTest;

abstract class AbstractFindTest extends AbstractTest
{
    use GetDataSetTrait;

    protected static $customColumns = ['new_lft' => 'lft', 'rgt', 'text' => 'value'];
}