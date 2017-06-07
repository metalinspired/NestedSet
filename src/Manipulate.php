<?php

namespace metalinspired\NestedSet;

use metalinspired\NestedSet\Exception;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\Adapter\Driver\StatementInterface;
use Zend\Db\Sql\Delete;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Insert;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Update;

class Manipulate extends AbstractNestedSet
{
    use CreateInsertGapTrait;

    /**
     * Constants for move method
     */
    const MOVE_AFTER = 'after',
        MOVE_BEFORE = 'before',
        MOVE_MAKE_CHILD = 'make_child';

    /**
     * Creates a statement for closing gap
     * Statement has following placeholders:
     *  :source1
     *  :size1
     *  :source2
     *  :size2
     *  :source3
     *
     * @return StatementInterface
     */
    protected function getCloseGapStatement()
    {
        if (!array_key_exists('close_gap', $this->statements)) {
            $closeGapStatement = new Update($this->table);

            $closeGapStatement
                ->set([
                    $this->leftColumn => new Expression(
                        '(CASE WHEN ? > :source1 THEN ? - :size1 ELSE ? END)',
                        [
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    $this->rightColumn => new Expression(
                        '(CASE WHEN ? > :source2 THEN ? - :size2 ELSE ? END)',
                        [
                            [$this->rightColumn => Expression::TYPE_IDENTIFIER],
                            [$this->rightColumn => Expression::TYPE_IDENTIFIER],
                            [$this->rightColumn => Expression::TYPE_IDENTIFIER]
                        ]
                    )
                ])
                ->where
                ->greaterThan(
                    $this->rightColumn,
                    new Expression(':source3')
                );

            $this->statements['close_gap'] = $this->sql->prepareStatementForSqlObject($closeGapStatement);
        }

        return $this->statements['close_gap'];
    }

    /**
     * Creates a statement for getting left and right values of a node
     * Statement has following placeholders:
     *  :id
     *
     * @return StatementInterface
     */
    protected function getCommonColumns()
    {
        if (!array_key_exists('common_columns', $this->statements)) {
            $select = new Select($this->table);

            $select
                ->columns([
                    'lft' => $this->leftColumn,
                    'rgt' => $this->rightColumn
                ])
                ->where
                ->equalTo(
                    $this->idColumn,
                    new Expression(':id')
                );

            $this->statements['common_columns'] = $this->sql->prepareStatementForSqlObject($select);
        }

        return $this->statements['common_columns'];
    }

    /**
     * Moves a range between, and including, provided left and right values
     *
     * @param int    $sourceLeft  Source node left value
     * @param int    $sourceRight Source node right value
     * @param int    $destination Destination for source node
     * @param string $position    Move node to before/after destination or make it a child of destination node
     * @return int Number of rows affected (Nodes moved)
     */
    protected function moveRange($sourceLeft, $sourceRight, $destination, $position)
    {
        // TODO: Insert checking if user is trying to make node child of its children

        /*
         * Prevent user from moving nodes before or after root node
         */
        if ($this->getRootNodeId() == $destination &&
            ($position == self::MOVE_BEFORE || $position == self::MOVE_AFTER)
        ) {
            throw new Exception\RuntimeException('Node(s) can not be moved before or after root node');
        }

        /*
         * Determine exact destination for moving node
         */
        $result = $this->getCommonColumns()->execute([':id' => $destination]);

        if (1 !== $result->getAffectedRows()) {
            throw new Exception\RuntimeException(sprintf(
                'Destination node with identifier %s was not found or not unique',
                $destination
            ));
        }

        switch ($position) {
            case self::MOVE_AFTER:
                $destination = (int)$result->current()['rgt'];
                break;
            case self::MOVE_BEFORE:
                $destination = (int)$result->current()['lft'];
                break;
            case self::MOVE_MAKE_CHILD:
                $destination = (int)$result->current()['rgt'] - 1;
        }

        /*
         * Check if node is moving backwards
         */
        $isNegativeMovement = $sourceLeft > $destination ? true : false;

        /*
         * Calculate size of moving node
         */
        $nodeSize = $sourceRight - $sourceLeft + 1;

        /*
         * Calculate size of movement
         */
        $movementSize = $destination - ($isNegativeMovement ? $sourceLeft - 1 : $sourceRight);

        /*
         * Move node to its new position
         */
        if (!array_key_exists('move', $this->statements)) {
            $moveStatement = new Update($this->table);

            $moveStatement
                ->set([
                    $this->leftColumn => new Expression(
                        '(CASE ' .
                        'WHEN ? BETWEEN :sourceLeft1 AND :sourceRight1 THEN ? + :increase1 ' .
                        'ELSE ? - :decrease1 END)',
                        [
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    $this->rightColumn => new Expression(
                        '(CASE ' .
                        'WHEN ? BETWEEN :sourceLeft2 AND :sourceRight2 THEN ? + :increase2 ' .
                        'WHEN ? = :rgtIgnore THEN ? ' .
                        'ELSE ? - :decrease2 ' .
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
                ->greaterThanOrEqualTo(
                    $this->leftColumn,
                    new Expression(':from')
                )
                ->lessThanOrEqualTo(
                    $this->rightColumn,
                    new Expression(':to')
                );

            $this->statements['move'] = $this->sql->prepareStatementForSqlObject($moveStatement);
        }

        /** @var StatementInterface $moveStatement */
        $moveStatement = $this->statements['move'];

        $result = $moveStatement->execute([
            ':sourceLeft1' => $sourceLeft,
            ':sourceRight1' => $sourceRight,
            ':increase1' => $movementSize,
            ':decrease1' => $nodeSize * ($isNegativeMovement ? -1 : 1),
            ':sourceLeft2' => $sourceLeft,
            ':sourceRight2' => $sourceRight,
            ':increase2' => $movementSize,
            ':rgtIgnore' => ($isNegativeMovement ? $sourceRight : $destination) + 1,
            ':decrease2' => $nodeSize * ($isNegativeMovement ? -1 : 1),
            ':from' => $sourceLeft + ($isNegativeMovement ? $movementSize : 0),
            ':to' => ($isNegativeMovement ? $sourceRight : $destination) + 1
        ]);

        return $result->getAffectedRows();
    }

    protected function deleteRange($left, $right)
    {
        /*
         * Calculate size of node
         */
        $size = $right - $left + 1;

        /*
         * Delete the node including its children
         */
        $delete = new Delete($this->table);

        $delete
            ->where
            ->greaterThanOrEqualTo(
                $this->leftColumn,
                $left
            )
            ->lessThanOrEqualTo(
                $this->rightColumn,
                $right
            );

        $result = $this->sql->prepareStatementForSqlObject($delete)->execute();

        /*
         * Close the gap left after deleting
         */
        $this->getCloseGapStatement()->execute([
            ':source1' => $right,
            ':size1' => $size,
            ':source2' => $right,
            ':size2' => $size,
            ':source3' => $right
        ]);

        return $result->getAffectedRows();
    }

    /**
     * Creates a root node
     *
     * @return string Root node identifier
     * @throws Exception\RuntimeException
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
            $this->rightColumn => 2
        ]);

        $result = $this->sql->prepareStatementForSqlObject($insert)->execute();

        return $result->getGeneratedValue();
    }

    /**
     * Inserts new node with provided data
     *
     * @param int|string $parent Identifier of parent node
     * @param array      $data   Data for new node
     * @return mixed|null Identifier for newly created node
     * @throws Exception\InvalidNodeIdentifierException
     * @throws Exception\RuntimeException
     *
     * TODO: add ability to insert after or before a node, like in move method
     */
    public function insert($parent, array $data = [])
    {
        if (!is_int($parent) && !is_string($parent)) {
            throw new Exception\InvalidNodeIdentifierException($parent, 'Parent');
        }

        /*
         * Get parents right column value as left column value for new node
         */
        $result = $this->getCommonColumns()->execute([':id' => $parent]);

        if (0 === $result->getAffectedRows()) {
            throw new Exception\RuntimeException(sprintf(
                "Parent with identifier %s was not found or not unique",
                $parent
            ));
        }
        $newPosition = (int)$result->current()['rgt'];

        /*
         * Create a gap to insert new record
         */
        $createGap = $this->getInsertMethodCreateGapStatement();

        $createGap->execute([
            ':newPosition' => $newPosition,
            ':newPositionWhere' => $newPosition
        ]);

        /*
         * Insert new data
         */
        $data[$this->leftColumn] = $newPosition;
        $data[$this->rightColumn] = $newPosition + 1;

        $insert = new Insert($this->table);

        $insert->values($data);

        $result = $this->sql->prepareStatementForSqlObject($insert)->execute();

        return $result->getGeneratedValue();
    }

    /**
     * Moves a node
     *
     * @param int|string $source      Identifier of source node
     * @param int|string $destination Identifier of destination node
     * @param string     $position    Move node to before/after destination or make it a child of destination node
     * @return int Number of affected rows (Nodes moved)
     * @throws Exception\RuntimeException
     * @throws Exception\InvalidArgumentException
     * @throws Exception\InvalidNodeIdentifierException
     */
    public function move($source, $destination, $position = self::MOVE_AFTER)
    {
        /*
         * Prevent user from moving root node
         */
        if ($this->getRootNodeId() == $source) {
            throw new Exception\RuntimeException('Root node can\'t be moved');
        }

        if (!is_int($source) && !is_string($source)) {
            throw new Exception\InvalidNodeIdentifierException($source, 'Source node');
        }

        if (!is_int($destination) && !is_string($destination)) {
            throw new Exception\InvalidNodeIdentifierException($destination, 'Destination node');
        }

        if (!is_string($position)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Method expects integer as $where parameter. Instance of %s given',
                is_object($position) ? get_class($position) : gettype($position)
            ));
        }

        if ($position !== self::MOVE_AFTER && $position !== self::MOVE_BEFORE && $position !== self::MOVE_MAKE_CHILD) {
            throw new Exception\InvalidArgumentException(sprintf(
                '$where parameter value can be either \'after\', \'before\' or \'make_child\'. \'%s\' given',
                $position
            ));
        }

        /*
         * Get left and right values of moving node
         */
        $result = $this->getCommonColumns()->execute([':id' => $source]);

        if (!$result instanceof ResultInterface || !$result->isQueryResult()) {
            throw new Exception\UnknownDbException();
        }

        if ($result->getAffectedRows() !== 1) {
            throw new Exception\RuntimeException(sprintf(
                'Source node with identifier %s was not found or not unique',
                $source
            ));
        }

        $sourceLeft = (int)$result->current()['lft'];
        $sourceRight = (int)$result->current()['rgt'];

        return $this->moveRange($sourceLeft, $sourceRight, $destination, $position);
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

    /**
     * Deletes a node
     *
     * @param int|string $id Node identifier
     * @return int number of rows affected (Nodes deleted)
     * @throws Exception\InvalidNodeIdentifierException
     * @throws Exception\RuntimeException
     */
    public function delete($id)
    {
        if (!is_int($id) && !is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        /*
         * Prevent user from deleting root node
         */
        if ($this->getRootNodeId() == $id) {
            throw new Exception\RuntimeException('Root node can\'t be deleted');
        }

        /*
         * Get right and left values of node that is being deleted
         */
        $result = $this->getCommonColumns()->execute([':id' => $id]);

        if (1 !== $result->getAffectedRows()) {
            throw new Exception\RuntimeException(sprintf(
                "Node with identifier: %s was not found or not unique",
                $id
            ));
        }

        $nodeLeft = (int)$result->current()['lft'];
        $nodeRight = (int)$result->current()['rgt'];

        return $this->deleteRange($nodeLeft, $nodeRight);
    }

    /**
     * Empties a node by removing its descendants
     * or by moving them to a new location
     *
     * @param int|string      $parent      Identifier of parent node
     * @param null|int|string $destination Identifier of destination node or null
     * @param string          $position    Move node to before/after destination or make it a child of destination node
     * @return int Number of affected rows (Nodes moved)
     * @throws Exception\InvalidArgumentException
     * @throws Exception\InvalidNodeIdentifierException
     * @throws Exception\RuntimeException
     */
    public function clean($parent, $destination = null, $position = self::MOVE_MAKE_CHILD)
    {
        if (!is_int($parent) && !is_string($parent)) {
            throw new Exception\InvalidNodeIdentifierException($parent);
        }

        if (!is_null($destination) && !is_int($destination) && !is_string($destination)) {
            throw new Exception\InvalidNodeIdentifierException($destination);
        }

        if (null !== $destination) {
            if (!is_string($position)) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Method expects integer as $where parameter. Instance of %s given',
                    is_object($position) ? get_class($position) : gettype($position)
                ));
            }

            if ($position !== self::MOVE_AFTER &&
                $position !== self::MOVE_BEFORE &&
                $position !== self::MOVE_MAKE_CHILD
            ) {
                throw new Exception\InvalidArgumentException(sprintf(
                    '$where parameter value can be either \'after\', \'before\' or \'make_child\'. \'%s\' given',
                    $position
                ));
            }
        }

        /*
         * Get left and right value of parent node
         */
        $result = $this->getCommonColumns()->execute([':id' => $parent]);

        if (1 !== $result->getAffectedRows()) {
            throw new Exception\RuntimeException(sprintf(
                "Parent node with identifier: %s was not found or not unique",
                $parent
            ));
        }

        $parentLeft = (int)$result->current()['lft'];
        $parentRight = (int)$result->current()['rgt'];

        /*
         * If parent node is empty bail
         */
        if ($parentLeft + 1 == $parentRight) {
            return 0;
        }

        $parentLeft++;
        $parentRight--;

        if (null !== $destination) {
            return $this->moveRange($parentLeft, $parentRight, $destination, $position);
        }

        return $this->deleteRange($parentLeft, $parentRight);
    }
}
