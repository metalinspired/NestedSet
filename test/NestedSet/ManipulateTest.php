<?php

namespace metalinspired\NestedSetTest\NestedSet;

use metalinspired\NestedSet\Exception\NodeChildOrSiblingToItself;
use metalinspired\NestedSet\Exception\RuntimeException;

class ManipulateTest extends AbstractManipulateTest
{
    public function testMoveNodeAfter()
    {
        $rows = self::$manipulate->moveAfter(6, 28);

        $this->assertEquals(24, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodeAfter.xml'
        );
    }

    public function testMoveNodeAfterBackwards()
    {
        $rows = self::$manipulate->moveAfter(18, 9);

        $this->assertEquals(8, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodeAfterBackwards.xml'
        );
    }

    public function testMoveNodeAfterRootNode()
    {
        $this->expectException(RuntimeException::class);

        self::$manipulate->moveAfter(20, 1);
    }

    public function testMoveNodeBefore()
    {
        $rows = self::$manipulate->moveBefore(12, 32);

        $this->assertEquals(20, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodeBefore.xml'
        );
    }

    public function testMoveNodeBeforeBackwards()
    {
        $rows = self::$manipulate->moveBefore(20, 4);

        $this->assertEquals(19, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodeBeforeBackwards.xml'
        );
    }

    public function testMoveNodeBeforeRootNode()
    {
        $this->expectException(RuntimeException::class);

        self::$manipulate->moveBefore(20, 1);
    }

    public function testMoveNodeMakeChild()
    {
        $rows = self::$manipulate->moveMakeChild(16, 36);

        $this->assertEquals(24, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodeMakeChild.xml'
        );
    }

    public function testMoveNodeMakeChildBackwards()
    {
        $rows = self::$manipulate->moveMakeChild(33, 13);

        $this->assertEquals(22, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/MoveNodeMakeChildBackwards.xml'
        );
    }

    public function testMoveRootNode()
    {
        $this->expectException(RuntimeException::class);

        self::$manipulate->move(1, 20);
    }

    public function testDeleteNode()
    {
        $rows = self::$manipulate->delete(3);

        $this->assertEquals(3, $rows);

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

    public function testClean()
    {
        $rows = self::$manipulate->clean(12);

        $this->assertEquals(9, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/Clean.xml'
        );
    }

    public function testCleanWithMoving()
    {
        $rows = self::$manipulate->clean(12, 4);

        $this->assertEquals(20, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/CleanWithMoving.xml'
        );
    }

    public function testMoveNodeToBeItsOwnChild()
    {
        $this->expectException(NodeChildOrSiblingToItself::class);

        self::$manipulate->moveAfter(2, 6);
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

    public function testMoveNodeToSamePosition()
    {
        $rows = self::$manipulate->moveAfter(15, 14);

        $this->assertEquals(0, $rows);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/Insert.xml'
        );
    }
}
