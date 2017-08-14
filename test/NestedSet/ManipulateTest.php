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
 *  3. Neither the name of the copyright holder nor the names of its
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
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
 * @license     http://opensource.org/licenses/BSD-3-Clause
 */

namespace metalinspired\NestedSetTest\NestedSet;

use metalinspired\NestedSet\Exception\NodeChildOrSiblingToItself;
use metalinspired\NestedSet\Exception\RuntimeException;

class ManipulateTest extends AbstractManipulateTest
{
    public function testMoveNodeAfter()
    {
        $rows = self::$manipulate->moveAfter(6, 28);

        $this->assertEquals(3, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodeAfter.xml'
        );
    }

    public function testMoveNodeAfterWithinSameParent()
    {
        $rows = self::$manipulate->moveAfter(12, 32);

        $this->assertEquals(10, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodeAfterWithinSameParent.xml'
        );
    }

    public function testMoveNodeAfterBackwards()
    {
        $rows = self::$manipulate->moveAfter(16, 9);

        $this->assertEquals(3, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodeAfterBackwards.xml'
        );
    }

    public function testMoveNodeAfterWithinSameParentBackwards()
    {
        $rows = self::$manipulate->moveAfter(32, 12);

        $this->assertEquals(10, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodeAfterWithinSameParentBackwards.xml'
        );
    }

    public function testMoveNodeAfterRootNode()
    {
        $this->expectException(RuntimeException::class);

        self::$manipulate->moveAfter(20, 1);
    }

    public function testMoveNodesAfter()
    {
        $rows = self::$manipulate->moveAfter([4, 5, 9, 13, 16, 39, 42], 24);

        $this->assertEquals(15, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodesAfter.xml'
        );
    }

    public function testMoveNodeBefore()
    {
        $rows = self::$manipulate->moveBefore(6, 28);

        $this->assertEquals(3, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodeBefore.xml'
        );
    }

    public function testMoveNodeBeforeWithinSameParent()
    {
        $rows = self::$manipulate->moveBefore(12, 32);

        $this->assertEquals(10, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodeBeforeWithinSameParent.xml'
        );
    }

    public function testMoveNodeBeforeBackwards()
    {
        $rows = self::$manipulate->moveBefore(20, 4);

        $this->assertEquals(1, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodeBeforeBackwards.xml'
        );
    }

    public function testMoveNodeBeforeWithinSameParentBackwards()
    {
        $rows = self::$manipulate->moveBefore(32, 12);

        $this->assertEquals(10, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodeBeforeWithinSameParentBackwards.xml'
        );
    }

    public function testMoveNodeBeforeFirstChild()
    {
        $rows = self::$manipulate->moveBefore(6, 27);

        $this->assertEquals(3, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodeBeforeFirstChild.xml'
        );
    }

    public function testMoveNodeBeforeFirstChildBackwards()
    {
        $rows = self::$manipulate->moveBefore(23, 4);

        $this->assertEquals(3, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodeBeforeFirstChildBackwards.xml'
        );
    }

    public function testMoveNodeBeforeRootNode()
    {
        $this->expectException(RuntimeException::class);

        self::$manipulate->moveBefore(20, 1);
    }

    public function testMoveNodesBefore()
    {
        $rows = self::$manipulate->moveBefore([4, 5, 9, 13, 16, 39, 42], 24);

        $this->assertEquals(15, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodesBefore.xml'
        );
    }

    public function testMoveNodeMakeChild()
    {
        $rows = self::$manipulate->moveMakeChild(16, 36);

        $this->assertEquals(3, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodeMakeChild.xml'
        );
    }

    public function testMoveNodeMakeChildBackwards()
    {
        $rows = self::$manipulate->moveMakeChild(33, 13);

        $this->assertEquals(3, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodeMakeChildBackwards.xml'
        );
    }

    public function testMoveNodesMakeChildren()
    {
        $rows = self::$manipulate->moveMakeChild([4, 5, 9, 13, 16, 39, 42], 36);

        $this->assertEquals(15, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodesMakeChildren.xml'
        );
    }

    public function testMoveNodeMakeChildOfEmptyNode()
    {
        $rows = self::$manipulate->moveMakeChild(16, 43);

        $this->assertEquals(3, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodeMakeChildOfEmptyNode.xml'
        );
    }

    public function testMoveNodeMakeChildOfEmptyNodeBackwards()
    {
        $rows = self::$manipulate->moveMakeChild(36, 24);

        $this->assertEquals(3, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodeMakeChildOfEmptyNodeBackwards.xml'
        );
    }

    public function testMoveNodesMakeChildrenOfEmptyNode()
    {
        $rows = self::$manipulate->moveMakeChild([4, 5, 9, 13, 16, 39, 42], 43);

        $this->assertEquals(15, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodesMakeChildrenOfEmptyNode.xml'
        );
    }

    public function testMoveRootNode()
    {
        $this->expectException(RuntimeException::class);

        self::$manipulate->move(1, 20);
    }

    public function testMoveNonExistingNode()
    {
        $this->expectException(RuntimeException::class);

        self::$manipulate->moveAfter(555, 15);
    }

    public function testMoveNonExistingNodes()
    {
        $this->expectException(RuntimeException::class);

        self::$manipulate->moveAfter([4, 5, 555, 9, 13, -15, 16, 39, 42], 24);
    }

    public function testMoveNodeToNonExistingDestination()
    {
        $this->expectException(RuntimeException::class);

        self::$manipulate->moveAfter(16, 555);
    }

    public function testMoveNodeToBeItsOwnChild()
    {
        $this->expectException(NodeChildOrSiblingToItself::class);

        self::$manipulate->moveMakeChild(2, 2);
    }

    public function testMoveNodesWhereOneOfSourceNodesWouldBeChildToItself()
    {
        $this->expectException(NodeChildOrSiblingToItself::class);

        self::$manipulate->moveMakeChild([12, 2, 32], 6);
    }

    public function testMoveNodeToBeSiblingToItSelf()
    {
        $rows = self::$manipulate->moveAfter(8, 8);

        $this->assertEquals(0, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/Insert.xml'
        );
    }

    public function testMoveNodesWhereOneOfSourceNodesWouldBeSiblingToItself()
    {
        $this->expectException(NodeChildOrSiblingToItself::class);

        self::$manipulate->moveAfter([2, 3, 5, 8, 12, 15], 8);
    }

    public function testDeleteNode()
    {
        $rows = self::$manipulate->delete([12, 22, 33, 38]);

        $this->assertEquals(24, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/Delete.xml'
        );
    }

    public function testDeleteNodeWhenChildrenInArray()
    {
        $rows = self::$manipulate->delete([12, 13, 14, 22, 33, 38]);

        $this->assertEquals(24, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/Delete.xml'
        );
    }

    public function testDeleteRootNode()
    {
        $this->expectException(RuntimeException::class);

        self::$manipulate->delete(1);
    }

    public function testDeleteNonExistingNode()
    {
        $this->expectException(RuntimeException::class);

        self::$manipulate->delete(100);
    }

    public function testCleanNode()
    {
        $rows = self::$manipulate->clean(12);

        $this->assertEquals(9, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/CleanNode.xml'
        );
    }

    public function testCleanNodes()
    {
        $rows = self::$manipulate->clean([13, 32, 16]);

        $this->assertEquals(13, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/CleanNodes.xml'
        );
    }

    public function testCleanWithMoving()
    {
        $rows = self::$manipulate->clean(12, 4);

        $this->assertEquals(9, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/CleanNodeWithMoving.xml'
        );
    }

    public function testCleanNodesWithMoving()
    {
        $rows = self::$manipulate->clean([13, 16, 32], 4);

        $this->assertEquals(13, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/CleanNodesWithMoving.xml'
        );
    }
}
