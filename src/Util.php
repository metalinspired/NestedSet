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

namespace metalinspired\NestedSet;

use metalinspired\NestedSet\Exception\RuntimeException;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Sql;

class Util
{
    protected static function checkTableEmpty(Config $config)
    {
        $select = new Select($config->table);

        $select->columns([
            'count' => new Expression(
                'COUNT(*)'
            )
        ]);

        $result = (new Sql($config->adapter))
            ->prepareStatementForSqlObject($select)
            ->execute();

        return $result->current()['count'] > 0 ? false : true;
    }

    protected static function getRootNode(Config $config)
    {
        $selectLft = new Select($config->table);
        $selectLft
            ->columns([
                'lft' => new Expression('MIN(' . $config->leftColumn . ')')
            ], false);

        $selectRgt = new Select($config->table);
        $selectRgt
            ->columns([
                'rgt' => new Expression('MAX(' . $config->rightColumn . ')')
            ], false);

        $select = new Select(['root' => $config->table]);
        $select
            ->join(
                ['lft' => $selectLft],
                new Expression('1=1'),
                []
            )
            ->join(
                ['rgt' => $selectRgt],
                new Expression('1=1'),
                []
            )
            ->where
            ->equalTo(
                "root.{$config->leftColumn}",
                new Expression(
                    '?',
                    [
                        ["lft.lft" => Expression::TYPE_IDENTIFIER]
                    ]
                )
            )
            ->equalTo(
                "root.{$config->rightColumn}",
                new Expression(
                    '?',
                    [
                        ["rgt.rgt" => Expression::TYPE_IDENTIFIER]
                    ]
                )
            );

        $result = (new Sql($config->adapter))
            ->prepareStatementForSqlObject($select)
            ->execute();

        if (1 !== $result->getAffectedRows()) {
            throw new RuntimeException('Starting left and ending right values does not belong to root node');
        }

        return $result->current();
    }

    /**
     * Checks validity of nested set
     *
     * @param Config $config        Configuration object
     * @param bool   $useExceptions Set to true to throw exceptions instead of returning error description
     * @throws RuntimeException
     * @return bool|string True if no errors were found, error description, if set by attribute, otherwise
     */
    public static function checkNestedSet(Config $config, $useExceptions = false)
    {
        try {
            if (null === $config->rootNodeId) {
                throw new RuntimeException('Root node identifier not set');
            }

            if (self::checkTableEmpty($config)) {
                throw new RuntimeException('Table is empty');
            }

            $root = self::getRootNode($config);

            /*
             * If there is only root element present return true
             */
            if ($root['lft'] + 1 == $root['rgt']) {
                return true;
            }

            $sql = new Sql($config->adapter);

            $select = new Select($config->table);

            $select
                ->columns([
                    'id' => $config->idColumn,
                    'lft' => $config->leftColumn,
                    'rgt' => $config->rightColumn
                ])
                ->order($config->leftColumn)
                ->where
                ->greaterThan(
                    $config->leftColumn,
                    $root['lft']
                )
                ->lessThan(
                    $config->rightColumn,
                    $root['rgt']
                );

            $nodes = $sql->prepareStatementForSqlObject($select)->execute();

            $stack = [];
            $isFirst = true;

            foreach ($nodes as $node) {
                /*
                 * If this is first node check for space between it and root node
                 */
                if ($isFirst && $node['lft'] - 1 != $root['lft']) {
                    throw new RuntimeException(sprintf(
                        'Gap exists between first node (%s) and root node (%s)',
                        $nodes->current()['id'],
                        $root['id']
                    ));
                }

                $isFirst = false;

                if (!empty($stack)) {
                    /*
                     * Fetch last node from stack
                     */
                    $previous = end($stack);

                    /*
                     * Check if current and previous node have same left or right values
                     */
                    if ($previous['lft'] == $node['lft']
                        || $previous['rgt'] == $node['rgt']
                    ) {
                        throw new RuntimeException(sprintf(
                            'Node %s and node %s have equal left or right values',
                            $previous['id'],
                            $node['id']
                        ));
                    }

                    /*
                     * Calculate size of previous node
                     */
                    $previousSize = (int)($previous['rgt'] - $previous['lft']) + 1;

                    /*
                     * If previous size is a parent it should have size greater than two
                     */
                    if ($previousSize > 2) {
                        /*
                         * Check if node has enough space to accommodate at least one node
                         */
                        if ($previousSize == 3) {
                            throw new RuntimeException(sprintf(
                                'Node %s should be a parent but there is not enough space to accommodate a single node',
                                $previous['id']
                            ));
                        }

                        /*
                         * Check if current node is a child of previous node
                         */
                        if ($previous['lft'] < $node['lft'] && $previous['rgt'] > $node['rgt']) {
                            if ($previous['lft'] + 1 != $node['lft']) {
                                throw new RuntimeException(sprintf(
                                    'There is a space between node %s left value and its parent %s left value. ' .
                                    'Missing first child?!?',
                                    $node['id'],
                                    $previous['id']
                                ));
                            }
                        } else {
                            if ($previous['rgt'] > $node['lft'] && $previous['rgt'] <= $node['rgt']) {
                                throw new RuntimeException(sprintf(
                                    'Node %s has its left value as child of node %s but its right value is ' .
                                    'greater than or equal to node %s value',
                                    $node['id'],
                                    $previous['id'],
                                    $previous['id']
                                ));
                            }
                            throw new RuntimeException(sprintf(
                                'Node %s should be a parent but it has no children',
                                $previous['id']
                            ));
                        }
                    } else {
                        /*
                         * Remove previous node from stack
                         */
                        array_pop($stack);

                        /*
                         * Check if previous node is last child of its parent
                         */
                        if ($previous['rgt'] + 1 < $node['lft']) {
                            /*
                             * Traverse stack backwards and see if nodes are last children of previous nodes
                             */
                            while (!empty($stack)) {
                                if ($previous['rgt'] + 1 == end($stack)['rgt']) {
                                    $previous = array_pop($stack);
                                } else {
                                    /*
                                     * If current node is not sibling of previous node than there is last child missing
                                     */
                                    if ($previous['rgt'] + 1 != $node['lft']
                                        && end($stack)['rgt'] < $node['lft']
                                    ) {
                                        var_dump($node);
                                        throw new RuntimeException(sprintf(
                                            'There is a space between node %s right value ' .
                                            'and its parent node %s right value. Missing last child?!?',
                                            $previous['id'],
                                            end($stack)['id']
                                        ));
                                    }
                                    break;
                                }
                            }

                            /*
                             * Check for space between siblings
                             * This could also indicate that parent is missing for current node
                             */
                            if ($previous['rgt'] + 1 < $node['lft']) {
                                throw new RuntimeException(sprintf(
                                    'There is space between node %s and its sibling node %s',
                                    $previous['id'],
                                    $node['id']
                                ));
                            }
                        }
                    }
                }

                switch (true) {
                    case $node['lft'] == $node['rgt']:
                        throw new RuntimeException(sprintf(
                            'Node %s has left value equal to its right value',
                            $node['id']
                        ));
                        break;

                    case $node['lft'] > $node['rgt']:
                        throw new RuntimeException(sprintf(
                            'Node %s has left value greater than its right value',
                            $node['id']
                        ));
                        break;

                    case !is_numeric($node['lft']) || !is_numeric($node['rgt']):
                        throw new RuntimeException(sprintf(
                            'Node %s left or right value are not numbers',
                            $node['id']
                        ));
                        break;

                    default:
                        $stack[] = $node;
                }
            }

            $node = array_pop($stack);

            if ($node['rgt'] + 1 < $root['rgt']) {
                throw new RuntimeException(sprintf(
                    'Gap exists between last node %s and root node',
                    $node['id']
                ));
            } elseif ($node['rgt'] >= $root['rgt']) {
                throw new RuntimeException(sprintf(
                    'Node %s right value is greater or equal to root node right value',
                    $node['id']
                ));
            }
        } catch (RuntimeException $exception) {
            if ($useExceptions) {
                throw $exception;
            }

            return $exception->getMessage();
        }

        return true;
    }
}
