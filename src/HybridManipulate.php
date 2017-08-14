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

namespace metalinspired\NestedSet;

use metalinspired\NestedSet\Exception\InvalidArgumentException;
use metalinspired\NestedSet\Exception\InvalidNodeIdentifierException;
use metalinspired\NestedSet\Exception\RuntimeException;
use metalinspired\NestedSet\Exception\UnknownDbException;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\Adapter\Driver\StatementInterface;
use Zend\Db\Sql\Delete;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Insert;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Update;

class HybridManipulate extends Manipulate
{
    use HybridNestedSetTrait;

    /**
     * Constants for move method
     */
    const MOVE_AFTER = 'after',
        MOVE_BEFORE = 'before',
        MOVE_MAKE_CHILD = 'make_child';

    /**
     * {@inheritdoc}
     */
    protected function getCommonColumns()
    {
        return [
            'id' => $this->idColumn,
            'lft' => $this->leftColumn,
            'rgt' => $this->rightColumn,
            'parent' => $this->parentColumn,
            'ordering' => $this->orderingColumn,
            'depth' => $this->depthColumn
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function findChildren(array $parents)
    {
        $select = new Select($this->table);
        $select
            ->columns([
                'id' => $this->idColumn
            ])
            ->where
            ->in(
                $this->parentColumn,
                $parents
            );

        $result = $this->sql->prepareStatementForSqlObject($select)->execute();

        if (! $result instanceof ResultInterface && ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        $children = [];

        foreach ($result as $node) {
            $children[] = $node['id'];
        }

        return $children;
    }

    /**
     * {@inheritdoc}
     */
    protected function moveRange($sourceRange, $destination, $position = self::MOVE_AFTER)
    {
        /*
         * Get destination node
         */
        $destinationNode = $this->findNodes([$destination]);

        if (! $destinationNode instanceof ResultInterface || ! $destinationNode->isQueryResult()) {
            throw new UnknownDbException();
        }

        if (1 !== $destinationNode->getAffectedRows()) {
            throw new Exception\RuntimeException(sprintf(
                'Destination node with identifier %s was not found or not unique',
                $destination
            ));
        }

        $destinationNode = $this->arrayValuesToInt($destinationNode->current());

        /*
         * Get destination parent
         * If position is set to MOVE_MAKE_CHILD than destination is actually
         * parent of destination and we need to get actual destination node
         */
        if ($position === self::MOVE_MAKE_CHILD) {
            $destinationParent = $destinationNode;

            if (! array_key_exists('hybrid_move_', $this->statements)) {
                $select = new Select($this->table);

                $select
                    ->columns($this->getCommonColumns())
                    ->where
                    ->equalTo(
                        $this->rightColumn,
                        new Expression(':childLeft')
                    );

                $this->statements['hybrid_move_'] = $this->sql->prepareStatementForSqlObject($select);
            }

            /** @var StatementInterface $destinationNodeStatement */
            $destinationNodeStatement = $this->statements['hybrid_move_'];

            $result = $destinationNodeStatement->execute([':childLeft' => $destinationParent['rgt'] - 1]);

            if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
                throw new UnknownDbException();
            }

            /*
             * If parent node has no children create artificial destination node
             */
            if (1 !== $result->getAffectedRows()) {
                $destinationNode = $destinationParent;
                $destinationNode['parent'] = $destinationNode['id'];
                $destinationNode['ordering'] = 0;
                $destinationNode['depth']++;
                $destinationNode['rgt'] = $destinationNode['lft'];
            } else {
                $destinationNode = $this->arrayValuesToInt($result->current());
            }
        } else {
            $destinationParent = $this->findNodes([$destinationNode['parent']]);

            if (1 !== $destinationParent->getAffectedRows()) {
                throw new Exception\RuntimeException(sprintf(
                    'Destination node parent with identifier %s was not found or not unique',
                    $destinationNode['parent']
                ));
            }

            $destinationParent = $this->arrayValuesToInt($destinationParent->current());
        }

        /*
         * Check if a node in range is being set as child/sibling of itself or own descendants
         */
        if ($destinationNode['lft'] > $sourceRange['lft'] && $destinationNode['rgt'] <= $sourceRange['rgt']) {
            throw new Exception\NodeChildOrSiblingToItself();
        }

        /*
         * Determine exact destination position
         */
        switch ($position) {
            case self::MOVE_AFTER:
            case self::MOVE_MAKE_CHILD:
                $destinationPosition = $destinationNode['rgt'];
                break;
            case self::MOVE_BEFORE:
                $destinationPosition = $destinationNode['lft'] - 1;
                break;
            default:
                throw new Exception\RuntimeException('Unknown position');
        }

        /*
         * Get destination ordering
         */
        $destinationOrdering = $destinationNode['ordering'];

        /*
         * Get source node parent
         */
        $sourceParent = $this->findNodes([$sourceRange['parent']]);

        if (1 !== $sourceParent->getAffectedRows()) {
            throw new Exception\RuntimeException(sprintf(
                'Source node parent with identifier %s was not found or not unique',
                $sourceRange['parent']
            ));
        }

        $sourceParent = $this->arrayValuesToInt($sourceParent->current());

        /*
         * Check if source node is being moved backward
         */
        $isNegativeMovement = $sourceRange['lft'] > $destinationPosition;

        /*
         * Save source left and right values here since they can change in case
         * of negative movement and we need their true values for depth and ordering changes
         */
        $sourceLeft = $sourceRange['lft'];
        $sourceRight = $sourceRange['rgt'];

        /*
         * If node is moving backwards flip source range and nodes affected by move
         */
        if ($isNegativeMovement) {
            $movementSize = $sourceRange['lft'] - $destinationPosition - 1;
            $nodeSize = $sourceRange['rgt'] - $sourceRange['lft'] + 1;
            $destinationPosition = $sourceRange['rgt'];
            $sourceRange['lft'] -= $movementSize;
            $sourceRange['rgt'] -= $nodeSize;
        }

        /*
         * Calculate size of moving node
        /*
         */
        $nodeSize = $sourceRange['rgt'] - $sourceRange['lft'] + 1;

        /*
         * Calculate size of movement
         */
        /*
         */
        $movementSize = $destinationPosition - $sourceRange['rgt'];

        /*
         * Determine end of range in which nodes need to be updated
         */
        $updateRangeEnd = $sourceParent['rgt'] > $destinationParent['rgt']
            ? $sourceParent['rgt']
            : $destinationParent['rgt'];

        /*
         * Prepare ordering ranges and adjust destination ordering
         */
        if ($sourceRange['parent'] != $destinationNode['parent']) {
            $orderingIncreaseStart = $destinationNode['rgt'];
            $orderingIncreaseEnd = $destinationParent['rgt'];
            $orderingDecreaseStart = $sourceRight;
            $orderingDecreaseEnd = $sourceParent['rgt'];

            switch ($position) {
                case self::MOVE_BEFORE:
                    // Destination node needs to be moved too
                    $orderingIncreaseStart = $destinationNode['lft'];
                    break;
                // MOVE_AFTER
                default:
                    $destinationOrdering++;
            }
        } else {
            if ($isNegativeMovement) {
                $orderingIncreaseStart = $sourceRange['lft'];
                $orderingIncreaseEnd = $sourceRange['rgt'];
                $orderingDecreaseStart = 0;
                $orderingDecreaseEnd = 0;

                switch ($position) {
                    case self::MOVE_AFTER:
                        $destinationOrdering++;
                        break;
                }
            } else {
                $orderingIncreaseStart = 0;
                $orderingIncreaseEnd = 0;
                $orderingDecreaseStart = $sourceRight;
                $orderingDecreaseEnd = $destinationNode['lft'];

                switch ($position) {
                    case self::MOVE_BEFORE:
                        // Destination node doesn't need to change ordering
                        $orderingDecreaseEnd--;
                        // Destination ordering is ordering of item before
                        $destinationOrdering--;
                        break;
                }
            }
        }

        /*
         * Move nodes
         */
        if (! array_key_exists('hybrid_move', $this->statements)) {
            $update = new Update($this->table);

            $update
                ->set([
                    $this->depthColumn => new Expression(
                        '(CASE ' .
                        'WHEN ? BETWEEN :depthStart AND :depthEnd THEN :destinationDepth + (? - :sourceDepth) ' .
                        'ELSE ? ' .
                        'END)',
                        [
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->depthColumn => Expression::TYPE_IDENTIFIER],
                            [$this->depthColumn => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    $this->parentColumn => new Expression(
                        '(CASE ' .
                        'WHEN ? BETWEEN :parentStart AND :parentEnd' .
                        ' AND ? = :sourceParentId1 THEN :destinationParentId1 ' .
                        'ELSE ? ' .
                        'END)',
                        [
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->parentColumn => Expression::TYPE_IDENTIFIER],
                            [$this->parentColumn => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    $this->orderingColumn => new Expression(
                        '(CASE ' .
                        'WHEN ? BETWEEN :orderingStart AND :orderingEnd' .
                        ' AND ? = :destinationParentId2 THEN (? - :orderingNormalize) + :destinationOrdering ' .
                        'WHEN ? BETWEEN :orderingIncreaseStart AND :orderingIncreaseEnd' .
                        ' AND ? = :destinationParent THEN ? + :increaseSize ' .
                        'WHEN ? BETWEEN :orderingDecreaseStart AND :orderingDecreaseEnd' .
                        ' AND ? = :sourceParentId2 THEN ? - :decreaseSize ' .
                        'ELSE ? ' .
                        'END)',
                        [
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->parentColumn => Expression::TYPE_IDENTIFIER],
                            [$this->orderingColumn => Expression::TYPE_IDENTIFIER],
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->parentColumn => Expression::TYPE_IDENTIFIER],
                            [$this->orderingColumn => Expression::TYPE_IDENTIFIER],
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->parentColumn => Expression::TYPE_IDENTIFIER],
                            [$this->orderingColumn => Expression::TYPE_IDENTIFIER],
                            [$this->orderingColumn => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    $this->leftColumn => new Expression(
                        '(CASE ' .
                        'WHEN ? BETWEEN :increase1start AND :increase1end THEN ? + :increase1 ' .
                        'WHEN ? BETWEEN :decrease1start AND :decrease1end THEN ? - :decrease1 ' .
                        'ELSE ? END)',
                        [
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    $this->rightColumn => new Expression(
                        '(CASE ' .
                        'WHEN ? BETWEEN :increase2start AND :increase2end THEN ? + :increase2 ' .
                        'WHEN ? BETWEEN :decrease2start AND :decrease2end THEN ? - :decrease2 ' .
                        'ELSE ? ' .
                        'END)',
                        [
                            [$this->rightColumn => Expression::TYPE_IDENTIFIER],
                            [$this->rightColumn => Expression::TYPE_IDENTIFIER],
                            [$this->rightColumn => Expression::TYPE_IDENTIFIER],
                            [$this->rightColumn => Expression::TYPE_IDENTIFIER],
                            [$this->rightColumn => Expression::TYPE_IDENTIFIER]
                        ]
                    )
                ])
                ->where
                ->between(
                    $this->leftColumn,
                    new Expression(':from1'),
                    new Expression(':to1')
                )
                ->or
                ->between(
                    $this->rightColumn,
                    new Expression(':from2'),
                    new Expression(':to2')
                );

            $this->statements['hybrid_move'] = $this->sql->prepareStatementForSqlObject($update);
        }

        /** @var StatementInterface $moveStatement */
        $moveStatement = $this->statements['hybrid_move'];

        $parameters = [
            // Depth
            ':depthStart' => $sourceLeft,
            ':depthEnd' => $sourceRight,
            ':destinationDepth' => $destinationNode['depth'],
            ':sourceDepth' => $sourceRange['depth'],
            // Parent
            ':parentStart' => $sourceLeft,
            ':parentEnd' => $sourceRight,
            ':sourceParentId1' => $sourceRange['parent'],
            ':destinationParentId1' => $destinationParent['id'],
            // Ordering
            ':orderingStart' => $sourceLeft,
            ':orderingEnd' => $sourceRight,
            ':destinationParentId2' => $destinationParent['id'],
            ':orderingNormalize' => $sourceRange['ordering'],
            ':destinationOrdering' => $destinationOrdering,
            ':orderingIncreaseStart' => $orderingIncreaseStart,
            ':orderingIncreaseEnd' => $orderingIncreaseEnd,
            ':destinationParent' => $destinationNode['parent'],
            ':increaseSize' => $sourceRange['count'],
            ':orderingDecreaseStart' => $orderingDecreaseStart,
            ':orderingDecreaseEnd' => $orderingDecreaseEnd,
            ':sourceParentId2' => $sourceRange['parent'],
            ':decreaseSize' => $sourceRange['count'],
            // Left
            ':increase1start' => $sourceRange['lft'],
            ':increase1end' => $sourceRange['rgt'],
            ':increase1' => $movementSize,
            ':decrease1start' => $sourceRange['rgt'] + 1,
            ':decrease1end' => $sourceRange['rgt'] + $movementSize,
            ':decrease1' => $nodeSize,
            // Right
            ':increase2start' => $sourceRange['lft'],
            ':increase2end' => $sourceRange['rgt'],
            ':increase2' => $movementSize,
            ':decrease2start' => $sourceRange['rgt'] + 1,
            ':decrease2end' => $sourceRange['rgt'] + $movementSize,
            ':decrease2' => $nodeSize,
            // Where
            ':from1' => $sourceRange['lft'],
            ':to1' => $updateRangeEnd,
            ':from2' => $sourceRange['lft'],
            ':to2' => $updateRangeEnd
        ];

        $result = $moveStatement->execute($parameters);

        if (! $result instanceof ResultInterface) {
            throw new UnknownDbException();
        }

        return $result->getAffectedRows();
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteRange($range)
    {
        /*
         * Calculate size of range
         */
        $size = $range['rgt'] - $range['lft'] + 1;

        $parent = new Select($this->table);
        $parent
            ->columns([
                'id' => $this->idColumn,
                'rgt' => $this->rightColumn
            ])
            ->where
            ->equalTo(
                $this->idColumn,
                new Expression(':id')
            );

        $parameters = [
            ':id' => $range['parent']
        ];

        $result = $this->sql->prepareStatementForSqlObject($parent)->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new Exception\UnknownDbException();
        }

        if (1 !== $result->getAffectedRows()) {
            throw new Exception\RuntimeException(sprintf(
                'Parent node of node %s was not found or not unique',
                $range['id']
            ));
        }

        $parent = $this->arrayValuesToInt($result->current());

        /*
         * Delete range
         */
        if (! array_key_exists('hybrid_delete_range', $this->statements)) {
            $delete = new Delete($this->table);

            $delete
                ->where
                ->greaterThanOrEqualTo(
                    $this->leftColumn,
                    new Expression(':from')
                )
                ->lessThanOrEqualTo(
                    $this->rightColumn,
                    new Expression(':to')
                );

            $this->statements['hybrid_delete_range'] = $this->sql->prepareStatementForSqlObject($delete);
        }

        /** @var StatementInterface $deleteRangeStatement */
        $deleteRangeStatement = $this->statements['hybrid_delete_range'];

        $parameters = [
            ':from' => $range['lft'],
            ':to' => $range['rgt']
        ];

        $result = $deleteRangeStatement->execute($parameters);

        if (! $result instanceof ResultInterface) {
            throw new UnknownDbException();
        }

        $count = $result->getAffectedRows();

        /*
         * Close the gap left after deleting
         */
        if (! array_key_exists('hybrid_close_gap', $this->statements)) {
            $update = new Update($this->table);

            $update
                ->set([
                    $this->orderingColumn => new Expression(
                        '(CASE ' .
                        'WHEN ? BETWEEN :orderingDecreaseStart AND :orderingDecreaseEnd' .
                        ' AND ? = :orderingParentId THEN ? - :orderingDecreaseSize ' .
                        'ELSE ? ' .
                        'END)',
                        [
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->parentColumn => Expression::TYPE_IDENTIFIER],
                            [$this->orderingColumn => Expression::TYPE_IDENTIFIER],
                            [$this->orderingColumn => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    $this->leftColumn => new Expression(
                        '(CASE ' .
                        'WHEN ? < :leftDecreaseStart THEN ? ' .
                        'ELSE ? - :leftDecrease ' .
                        'END)',
                        [
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    $this->rightColumn => new Expression(
                        '? - :rightDecrease',
                        [
                            [$this->rightColumn => Expression::TYPE_IDENTIFIER]
                        ]
                    )
                ])
                ->where
                ->greaterThanOrEqualTo(
                    $this->rightColumn,
                    new Expression(':start')
                );

            $this->statements['hybrid_close_gap'] = $this->sql->prepareStatementForSqlObject($update);
        }

        /** @var StatementInterface $closeGapStatement */
        $closeGapStatement = $this->statements['hybrid_close_gap'];

        $parameters = [
            ':orderingDecreaseStart' => $range['rgt'],
            ':orderingDecreaseEnd' => $parent['rgt'],
            ':orderingParentId' => $parent['id'],
            ':orderingDecreaseSize' => $range['count'],
            ':leftDecreaseStart' => $range['rgt'],
            ':leftDecrease' => $size,
            ':rightDecrease' => $size,
            ':start' => $range['rgt']
        ];

        $result = $closeGapStatement->execute($parameters);

        if (! $result instanceof ResultInterface) {
            throw new UnknownDbException();
        }

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function createRootNode()
    {
        $select = new Select($this->table);

        $select->columns([
            'count' => new Expression(
                'COUNT(?)',
                [
                    [$this->idColumn => Expression::TYPE_IDENTIFIER]
                ]
            )
        ]);

        $result = $this->sql->prepareStatementForSqlObject($select)->execute();

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        if ($result->current()['count'] != 0) {
            throw new Exception\RuntimeException(
                sprintf(
                    'Can\'t create root node. Table %s is not empty',
                    $this->table
                )
            );
        }

        $insert = new Insert($this->table);

        $insert->values([
            $this->leftColumn => 1,
            $this->rightColumn => 2,
            $this->parentColumn => 0,
            $this->orderingColumn => 0,
            $this->depthColumn => 0
        ]);

        $result = $this->sql->prepareStatementForSqlObject($insert)->execute();

        if (! $result instanceof ResultInterface) {
            throw new UnknownDbException();
        }

        return $result->getGeneratedValue();
    }

    /**
     * {@inheritdoc}
     */
    public function insert($parent, array $data = [])
    {
        if (! is_int($parent) && ! is_string($parent)) {
            throw new Exception\InvalidNodeIdentifierException($parent, 'Parent');
        }

        /*
         * Get required parent fields
         */
        $result = $this->findNodes([$parent]);

        if (0 === $result->getAffectedRows()) {
            throw new Exception\RuntimeException(sprintf(
                "Parent with identifier %s was not found or not unique",
                $parent
            ));
        }
        $parent = $this->arrayValuesToInt($result->current());
        $newPosition = $parent['rgt'];

        /*
         * Create a gap to insert new record
         */
        $parameters = [
            ':newPosition' => $newPosition,
            ':newPositionWhere' => $newPosition
        ];

        $result = $this->getInsertMethodCreateGapStatement()->execute($parameters);

        if (! $result instanceof ResultInterface) {
            throw new UnknownDbException();
        }

        /*
         * Insert new data
         */
        $ordering = new Select($this->table);

        $ordering
            ->columns([
                'ordering' => new Expression(
                    'COALESCE(MAX(?),0)+1',
                    [
                        [$this->orderingColumn => Expression::TYPE_IDENTIFIER]
                    ]
                )
            ])
            ->where
            ->equalTo(
                $this->parentColumn,
                $parent['id']
            );

        $data[$this->leftColumn] = $newPosition;
        $data[$this->rightColumn] = $newPosition + 1;
        $data[$this->parentColumn] = $parent['id'];
        $data[$this->depthColumn] = $parent[$this->depthColumn] + 1;

        $data[$this->orderingColumn] = (new Select(['t' => $ordering]))
            ->columns([
                'ordering'
            ]);

        $insert = new Insert($this->table);

        $insert->values($data);

        $result = $this->sql->prepareStatementForSqlObject($insert)->execute();

        if (! $result instanceof ResultInterface) {
            throw new UnknownDbException();
        }

        return $result->getGeneratedValue();
    }

    /**
     * Move node within a same parent
     *
     * @param string|int $id    Node identifier
     * @param int        $order Order column value of target node or 0 to reorder as last node in parent
     * @return int Number of nodes moved
     * @throws InvalidNodeIdentifierException
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws UnknownDbException
     */
    public function reorder($id, $order)
    {
        if (! is_int($id) && ! is_string($id) /*&& ! is_array($id)*/) {
            // TODO: Exception does not state it can be array
            throw new InvalidNodeIdentifierException($id, 'Source node');
        }

        if (! is_int($order) || is_string($order) && ! is_numeric($order)) {
            throw new InvalidArgumentException('Order must be valid number');
        }

        $range = $this->sourcesToRange([$id])[0];

        if (1 <= $order) {
            // Find destination node
            $select = new Select($this->table);

            $select
                ->columns(['id' => $this->idColumn])
                ->where
                ->equalTo(
                    $this->orderingColumn,
                    new Expression(':order')
                )
                ->equalTo(
                    $this->parentColumn,
                    new Expression(':parent')
                );

            $parameters = [
                ':order' => $order,
                ':parent' => $range['parent']
            ];

            $result = $this->sql->prepareStatementForSqlObject($select)->execute($parameters);

            if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
                throw new UnknownDbException();
            }

            if (1 !== $result->getAffectedRows()) {
                throw new RuntimeException(sprintf(
                    'Node with order %s within parent %s not found or not unique',
                    $order,
                    $range['parent']
                ));
            }

            $destinationNodeId = (int)$result->current()['id'];

            $this->moveRange($range, $destinationNodeId, self::MOVE_BEFORE);
        } else {
            // Find last sibling
            $select = new Select($this->table);

            $select
                ->columns([
                    'id' => new Expression(
                        'MAX(?)',
                        [
                            [$this->idColumn => Expression::TYPE_IDENTIFIER]
                        ]
                    )
                ])
                ->where
                ->equalTo(
                    $this->parentColumn,
                    new Expression(':parent')
                );

            $parameters = [
                ':parent' => $range['parent']
            ];

            $result = $this->sql->prepareStatementForSqlObject($select)->execute($parameters);

            if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
                throw new UnknownDbException();
            }

            $destinationNodeId = (int)$result->current()['id'];

            $this->moveRange($range, $destinationNodeId, self::MOVE_AFTER);
        }

        return (int)($range['rgt'] - $range['lft'] + 1) / 2;
    }
}
