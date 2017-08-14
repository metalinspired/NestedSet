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

use metalinspired\NestedSet\Exception\RuntimeException;
use metalinspired\NestedSet\Manipulate;

class InsertTest extends AbstractManipulateTest
{

    public function getDataSet()
    {
        return $this->createMySQLXMLDataSet(__DIR__ . '/Fixture/CreateRoot.xml');
    }

    /**
     * @param Manipulate $nestedSet
     * @return void
     */
    protected function createNodes($nestedSet)
    {
        $nodeCount = 1;
        for ($i = 0; $i < 4; $i++) {
            $iNode = $nestedSet->insert(1, ['value' => 'Node ' . $nodeCount++]);
            for ($j = 0; $j < 3; $j++) {
                $jNode = $nestedSet->insert($iNode, ['value' => 'Node ' . $nodeCount++]);
                for ($k = 0; $k < 2; $k++) {
                    $nestedSet->insert($jNode, ['value' => 'Node ' . $nodeCount++]);
                }
            }
        }
        for ($i = 0; $i < 3; $i++) {
            $nestedSet->insert(1, ['value' => 'Node ' . $nodeCount++]);
        }
    }

    /*
     * Nested Set
     */

    public function testInsertNodes()
    {
        $this->createNodes(self::$manipulate);

        $this->assertTableAndFixtureEqual(
            $GLOBALS[self::DB_TABLE],
            __DIR__ . '/Fixture/Insert.xml'
        );
    }

    public function testCreateNodeInNonExistingParent()
    {
        $this->expectException(RuntimeException::class);

        self::$manipulate->insert(10);
    }
}
