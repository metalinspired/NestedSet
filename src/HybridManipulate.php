<?php

namespace metalinspired\NestedSet;

use metalinspired\NestedSet\Exception\RuntimeException;
use metalinspired\NestedSet\Exception\UnknownDbException;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\Adapter\Driver\StatementInterface;
use Zend\Db\Sql\Delete;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Insert;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Update;

class HybridManipulate extends AbstractNestedSet
{
    use HybridNestedSetTrait,
        CreateInsertGapTrait;

    /**
     * Constants for move method
     */
    const MOVE_AFTER = 'after',
        MOVE_BEFORE = 'before',
        MOVE_MAKE_CHILD = 'make_child';

    /**
     * @param array $identifiers
     * @return ResultInterface
     */
    protected function getCommonColumns(array $identifiers)
    {
        //TODO: make a version when only one id is requested cacheable ?!?

        $select = new Select($this->table);
        $select
            ->columns([
                'id' => $this->idColumn,
                'lft' => $this->leftColumn,
                'rgt' => $this->rightColumn,
                'parent' => $this->parentColumn,
                'ordering' => $this->orderingColumn,
                'depth' => $this->depthColumn
            ])
            ->order($this->leftColumn . ' ASC')
            ->where
            ->in(
                $this->idColumn,
                $identifiers
            );

        return $this->sql->prepareStatementForSqlObject($select)->execute();
    }

    /**
     * Checks if nodes are continuous siblings and builds a range to be moved
     *
     * @param array $sources            Identifiers of nodes
     * @param bool  $childrenBreakRange If set to false to make nodes that are children fall within valid range
     * @return array                    Array whose first element is a range array and, possibly, identifiers of nodes
     *                                  from sources array who were not direct siblings therefore did not fall
     *                                  into valid range
     */
    protected function sourcesToRange($sources, $childrenBreakRange = true)
    {
        if (!is_array($sources)) {
            $sources = [$sources];
        }

        /*
         * Get nodes and relevant data
         */
        $nodes = $this->getCommonColumns($sources);

        /*
         * Identifier of nodes that didn't fall within range of continuous nodes
         * Used to filter non existing nodes
         */
        $idsOutOfRange = [];

        /*
         * Range that is to be moved
         */
        $range = null;

        /*
         * Trigger for changing behavior when looping through nodes
         */
        $rangeComplete = false;

        /*
         * Right value of previous node
         */
        $right = null;

        /*
         * Check if nodes are continuous siblings
         */
        while ($node = $nodes->current()) {
            $node = $this->arrayValuesToInt($node);

            /*
             * Remove node from sources array
             */
            unset($sources[array_search($node['id'], $sources)]);

            if (!$rangeComplete) {
                if ($right && (
                        $right < $node['rgt'] && !$childrenBreakRange
                        || $node['lft'] !== $right + 1 && $childrenBreakRange
                    )
                ) {
                    $rangeComplete = true;
                    $idsOutOfRange[] = $node['id'];
                    $nodes->next();
                    continue;
                }
                if (!$range) {
                    $range = $node;
                    $range['count'] = 0;
                    $range['last_id'] = $node['id'];
                }
                if ($node['rgt'] > $right) {
                    $range['rgt'] = $node['rgt'];
                    $range['last_id'] = $node['id'];
                    $range['count']++;
                    $right = $node['rgt'];
                }
            } else {
                $idsOutOfRange[] = $node['id'];
            }

            /*
             * Advance to next node
             */
            $nodes->next();
        }

        /*
         * If sources array is not empty than it contains non-existing node identifier
         */
        if (!empty($sources)) {
            throw new RuntimeException(sprintf(
                'Node %s could not be found',
                reset($sources)
            ));
        }
        /*
         * Create a array composed of valid range as first element and
         * identifiers of nodes who do not fall within computed range
         */
        $range = array_merge([$range], $idsOutOfRange);

        return $range;
    }

    protected function arrayValuesToInt(array $array)
    {
        foreach ($array as &$value) {
            $value = (int)$value;
        }

        return $array;
    }

    protected function moveRange($sourceRange, $destination, $position = self::MOVE_AFTER)
    {
        //TODO: insert checks

        /*
         * Get destination node
         */
        $destinationNode = $this->getCommonColumns([':id' => $destination]);

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

            // TODO: put in cache
            $destinationNode = new Select($this->table);

            $destinationNode
                ->columns([
                    'lft' => $this->leftColumn,
                    'rgt' => $this->rightColumn,
                    'ordering' => $this->orderingColumn,
                    'parent' => $this->parentColumn,
                    'depth' => $this->depthColumn
                ])
                ->where
                ->equalTo(
                    $this->rightColumn,
                    $destinationParent['rgt'] - 1
                );

            $destinationNode = $this->sql->prepareStatementForSqlObject($destinationNode)->execute();

            // TODO: maybe check it there were multiple nodes found
            /*
             * If parent node has no children create artificial destination node
             */
            if (1 !== $destinationNode->getAffectedRows()) {
                $destinationNode = $destinationParent;
                $destinationNode['parent'] = $destinationNode['id'];
                $destinationNode['ordering'] = 0;
                $destinationNode['depth']++;
                $destinationNode['rgt'] = $destinationNode['lft'];
            } else {
                $destinationNode = $this->arrayValuesToInt($destinationNode->current());
            }
        } else {
            $destinationParent = $this->getCommonColumns([':id' => $destinationNode['parent']]);

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
            case self::MOVE_BEFORE:
                $destinationPosition = $destinationNode['lft'] - 1;
                break;
            case self::MOVE_MAKE_CHILD:
                $destinationPosition = $destinationNode['rgt'];
                break;
            // MOVE_AFTER
            default:
                $destinationPosition = $destinationNode['rgt'];
        }

        /*
         * Get destination ordering
         */
        $destinationOrdering = $destinationNode['ordering'];

        /*
         * Get source node parent
         */
        $sourceParent = $this->getCommonColumns([':id' => $sourceRange['parent']]);

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
        if (!array_key_exists('move', $this->statements)) {
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

            $this->statements['move'] = $this->sql->prepareStatementForSqlObject($update);
        }

        /** @var StatementInterface $moveStatement */
        $moveStatement = $this->statements['move'];

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

        return $result->getAffectedRows();
    }

    protected function deleteRange($range)
    {
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

        $parent = $this->sql->prepareStatementForSqlObject($parent)->execute($parameters);

        if (!$parent instanceof ResultInterface || !$parent->isQueryResult()) {
            throw new Exception\UnknownDbException();
        }

        if (1 !== $parent->getAffectedRows()) {
            throw new Exception\RuntimeException(sprintf(
                'Parent node of node %s was not found or not unique',
                $range['id']
            ));
        }

        $parent = $this->arrayValuesToInt($parent->current());

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

        $parameters = [
            ':from' => $range['lft'],
            ':to' => $range['rgt']
        ];

        $count = $this
            ->sql
            ->prepareStatementForSqlObject($delete)
            ->execute($parameters)
            ->getAffectedRows();

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
                $range['rgt']
            );

        $parameters = [
            ':orderingDecreaseStart' => $range['rgt'],
            ':orderingDecreaseEnd' => $parent['rgt'],
            ':orderingParentId' => $parent['id'],
            ':orderingDecreaseSize' => $range['count'],
            ':leftDecreaseStart' => $range['rgt'],
            ':leftDecrease' => $size,
            ':rightDecrease' => $size
        ];

        $this->sql->prepareStatementForSqlObject($update)->execute($parameters);

        return $count;
    }

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

        return $result->getGeneratedValue();
    }

    public function insert($parent, array $data = [])
    {
        if (!is_int($parent) && !is_string($parent)) {
            throw new Exception\InvalidNodeIdentifierException($parent, 'Parent');
        }

        /*
         * Get required parent fields
         */
        $result = $this->getCommonColumns([$parent]);

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
        $this
            ->getInsertMethodCreateGapStatement()
            ->execute([
                ':newPosition' => $newPosition,
                ':newPositionWhere' => $newPosition
            ]);

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

        return $result->getGeneratedValue();
    }


    /**
     * Move node(s)
     *
     * @param int|string|array $source      Identifier of source node or array of identifiers
     * @param int|string       $destination Identifier of destination node
     * @param string           $position    Move node to before/after destination or make it a child of destination node
     * @return int                          Number of nodes moved
     * @throws Exception\RuntimeException
     */
    public function move($source, $destination, $position = self::MOVE_AFTER)
    {
        /*
         * Bail early checks when moving single node and source and destination are same
         */
        if (!is_array($source) && $source == $destination) {
            if ($position === self::MOVE_MAKE_CHILD) {
                throw new Exception\NodeChildOrSiblingToItself();
            }
            return 0;
        }

        $source = $this->sourcesToRange($source);

        $sourceRange = array_shift($source);

        $count = ($sourceRange['rgt'] - $sourceRange['lft'] + 1) / 2;

        $this->moveRange($sourceRange, $destination, $position);

        /*
         * Check if there is more nodes that need to be moved
         * but change destination to after last moved node
         */
        if (!empty($source)) {
            $count += $this->move($source, $sourceRange['last_id'], self::MOVE_AFTER);
        }

        return $count;
    }

    /**
     * Move a node after destination node
     *
     * @see move()
     * @param $source
     * @param $destination
     * @return int
     */
    public function moveAfter($source, $destination)
    {
        return $this->move($source, $destination, self::MOVE_AFTER);
    }

    /**
     * Move a node before destination node
     *
     * @see move()
     * @param $source
     * @param $destination
     * @return int
     */
    public function moveBefore($source, $destination)
    {
        return $this->move($source, $destination, self::MOVE_BEFORE);
    }

    /**
     * Move a node to become a child of destination node
     *
     * @see move()
     * @param $source
     * @param $destination
     * @return int
     */
    public function moveMakeChild($source, $destination)
    {
        return $this->move($source, $destination, self::MOVE_MAKE_CHILD);
    }

    public function delete($id)
    {
        $id = $this->sourcesToRange($id, false);

        $range = array_shift($id);

        /*
         * Prevent user from deleting root node
         */
        if ($this->getRootNodeId() == $range['id']) {
            throw new Exception\RuntimeException('Root node can\'t be deleted');
        }

        $count = $this->deleteRange($range);

        if (!empty($id)) {
            $count += $this->delete($id);
        }

        return $count;
    }

    public function clean($parent, $destination = null, $position = self::MOVE_MAKE_CHILD)
    {
        if (!is_array($parent)) {
            $parent = [$parent];
        }

        $nodes = new Select($this->table);
        $nodes
            ->columns([
                'id' => $this->idColumn
            ])
            ->where
            ->in(
                $this->parentColumn,
                $parent
            );

        $nodes = $this->sql->prepareStatementForSqlObject($nodes)->execute();

        if (!$nodes instanceof ResultInterface && !$nodes->isQueryResult()) {
            throw new UnknownDbException();
        }

        if (!$nodes->getAffectedRows()) {
            return 0;
        }

        $children = [];

        foreach ($nodes as $node) {
            $children[] = $node['id'];
        }

        $nodes = null;

        if ($destination) {
            $count = $this->move($children, $destination, $position);
        } else {
            $count = $this->delete($children);
        }

        return $count;
    }
}
