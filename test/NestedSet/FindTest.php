<?php
/**
 * Copyright (c) 2017 Milan Divkovic.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *  1. Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @author      Milan Divkovic <metalinspired@gmail.com>
 * @copyright   2017 Milan Divkovic.
 * @license     http://opensource.org/licenses/BSD-license FreeBSD License
 */

namespace metalinspired\NestedSetTest\NestedSet;

use metalinspired\NestedSet\Config;
use metalinspired\NestedSet\Find;

class FindTest extends AbstractFindTest
{
    /**
     * @var Find
     */
    protected $find;

    public function setUp()
    {
        parent::setUp();

        $config = Config::createWithPdo(self::$pdo);
        $config->table = $GLOBALS[self::DB_TABLE];
        $config->rootNodeId = 1;

        $this->find = new Find($config);
    }

    public function testFindDescendants()
    {
        $result = $this->find->findDescendants(12);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindDescendants.xml'
        );
    }

    public function testFindChildren()
    {
        $result = $this->find->findChildren(12);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindChildren.xml'
        );
    }

    public function testFindAncestors()
    {
        $result = $this->find->findAncestors(38);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindAncestors.xml'
        );
    }

    public function testFindParent()
    {
        $result = $this->find->findParent(38);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindParent.xml'
        );
    }

    public function testFindFirstChild()
    {
        $result = $this->find->findFirstChild(22);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindFirstChild.xml'
        );
    }

    public function testFindLastChild()
    {
        $result = $this->find->findLastChild(22);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindLastChild.xml'
        );
    }

    public function testFindSiblings()
    {
        $result = $this->find->findSiblings(12);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindSiblings.xml'
        );
    }

    public function testFindNextSibling()
    {
        $result = $this->find->findNextSibling(22);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindNextSibling.xml'
        );
    }

    public function testFindPreviousSibling()
    {
        $result = $this->find->findPreviousSibling(22);

        $this->assertResultAndFixtureEqual(
            $result,
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/FindPreviousSibling.xml'
        );
    }
}
