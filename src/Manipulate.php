<?php

namespace metalinspired\NestedSet;

use metalinspired\NestedSet\Exception\InvalidArgumentException;
use metalinspired\NestedSet\Exception\InvalidNodeIdentifierException;
use metalinspired\NestedSet\Exception\NodeChildOrSiblingToItself;
use metalinspired\NestedSet\Exception\RuntimeException;
use metalinspired\NestedSet\Exception\UnknownDbException;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\Adapter\Driver\StatementInterface;
use Zend\Db\Sql\Delete;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Insert;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Update;

class Manipulate extends AbstractNestedSet
{
    /**
     * Constants for move method
     */
    const MOVE_AFTER = 'after',
        MOVE_BEFORE = 'before',
        MOVE_MAKE_CHILD = 'make_child';

    /**
     * Returns an array of most often used columns
     *
     * @return array
     */
    protected function getCommonColumns()
    {
        return [
            'id' => $this->idColumn,
            'lft' => $this->leftColumn,
            'rgt' => $this->rightColumn
        ];
    }

    /**
     * Finds nodes with columns set from getCommonColumns
     *
     * @param array $identifiers
     * @return ResultInterface
     */
    protected function findNodes(array $identifiers)
    {
        //TODO: make a version when only one id is requested cachable ?!?

        $select = new Select($this->table);
        $select
            ->columns($this->getCommonColumns())
            ->order($this->leftColumn . ' ASC')
            ->where
            ->in(
                $this->idColumn,
                $identifiers
            );

        $result = $this->sql->prepareStatementForSqlObject($select)->execute();

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
    }

    /**
     * Finds children of specified parents
     *
     * @param array $parents
     * @return array
     */
    protected function findChildren(array $parents)
    {
        $select = new Select(['q' => $this->table]);

        $select
            ->columns([])
            ->join(
                ['w' => $this->table],
                "w.{$this->leftColumn} >= q.{$this->leftColumn} " .
                "AND w.{$this->rightColumn} < q.{$this->rightColumn}",
                []
            )
            ->join(
                ['t' => $this->table],
                "t.{$this->leftColumn} BETWEEN w.{$this->leftColumn} AND w.{$this->rightColumn}",
                [
                    'id' => $this->idColumn
                ]
            )
            ->group("t.{$this->idColumn}");

        $select
            ->having
            ->equalTo(new Expression('COUNT(*)'), 1);

        $select
            ->where
            ->in(
                "q.{$this->idColumn}",
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
     * Checks if nodes are continuous siblings and creates a range to be moved
     *
     * @param array $sources            Identifiers of nodes
     * @param bool  $childrenBreakRange If set to false to make nodes that are children fall within valid range
     * @return array                    Array whose first element is a range array and, possibly, identifiers of nodes
     *                                  from sources array who were not direct siblings therefore did not fall
     *                                  into valid range
     */
    protected function sourcesToRange($sources, $childrenBreakRange = true)
    {
        if (! is_array($sources)) {
            $sources = [$sources];
        }

        /*
         * Get nodes and relevant data
         */
        $nodes = $this->findNodes($sources);

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

            if (! $rangeComplete) {
                if ($right && (
                        $right < $node['rgt'] && ! $childrenBreakRange
                        || $node['lft'] !== $right + 1 && $childrenBreakRange
                    )
                ) {
                    $rangeComplete = true;
                    $idsOutOfRange[] = $node['id'];
                    $nodes->next();
                    continue;
                }
                if (! $range) {
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
        if (! empty($sources)) {
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

    /**
     * Converts array values to integers
     *
     * @param array $array
     * @return array
     */
    protected function arrayValuesToInt(array $array)
    {
        foreach ($array as &$value) {
            $value = (int)$value;
        }

        return $array;
    }

    /**
     * Moves a range between, and including, provided left and right values
     *
     * @param array  $sourceRange Array with data about range of nodes that are to be moved
     * @param int    $destination Destination for source node
     * @param string $position    Move node to before/after destination or make it a child of destination node
     * @return int Number of rows affected
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
            throw new RuntimeException(sprintf(
                'Destination node with identifier %s was not found or not unique',
                $destination
            ));
        }

        $destinationNode = $this->arrayValuesToInt($destinationNode->current());

        /*
         * Check if a node in range is being set as child/sibling of itself or own descendants
         */
        if ($destinationNode['lft'] > $sourceRange['lft'] && $destinationNode['rgt'] <= $sourceRange['rgt']) {
            throw new NodeChildOrSiblingToItself();
        }

        /*
         * Determine exact destination for moving node
         */
        switch ($position) {
            case self::MOVE_AFTER:
                $destinationPosition = $destinationNode['rgt'];
                break;
            case self::MOVE_BEFORE:
                $destinationPosition = $destinationNode['lft'] - 1;
                break;
            case self::MOVE_MAKE_CHILD:
                $destinationPosition = $destinationNode['rgt'] - 1;
                break;
            default:
                throw new RuntimeException('Unknown position');
        }

        /*
         * If node is moving backwards flip source range and nodes affected by move
         */
        if ($sourceRange['lft'] > $destinationPosition) {
            $movementSize = $sourceRange['lft'] - $destinationPosition - 1;
            $nodeSize = $sourceRange['rgt'] - $sourceRange['lft'] + 1;
            $destinationPosition = $sourceRange['rgt'];
            $sourceRange['lft'] -= $movementSize;
            $sourceRange['rgt'] -= $nodeSize;
        }

        /*
         * Calculate size of moving node
         */
        $nodeSize = $sourceRange['rgt'] - $sourceRange['lft'] + 1;

        /*
         * Calculate size of movement
         */
        $movementSize = $destinationPosition - $sourceRange['rgt'];

        /*
         * Move nodes
         */
        if (! array_key_exists('move', $this->statements)) {
            $update = new Update($this->table);

            $update
                ->set([
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
            ':to1' => $destinationPosition + 1,
            ':from2' => $sourceRange['lft'],
            ':to2' => $destinationPosition + 1
        ];

        $result = $moveStatement->execute($parameters);

        if (! $result instanceof ResultInterface) {
            throw new UnknownDbException();
        }

        return $result->getAffectedRows();
    }

    /**
     * Deletes all nodes within a range
     *
     * @param $range
     * @return int Number of nodes deleted
     */
    protected function deleteRange($range)
    {
        /*
         * Calculate size of range
         */
        $size = $range['rgt'] - $range['lft'] + 1;

        /*
         * Delete range
         */
        if (! array_key_exists('delete_range', $this->statements)) {
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

            $this->statements['delete_range'] = $this->sql->prepareStatementForSqlObject($delete);
        }

        /** @var StatementInterface $deleteRangeStatement */
        $deleteRangeStatement = $this->statements['delete_range'];

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
        if (! array_key_exists('close_gap', $this->statements)) {
            $update = new Update($this->table);

            $update
                ->set([
                    $this->leftColumn => new Expression(
                        '(CASE WHEN ? > :leftDecreaseStart THEN ? - :leftDecrease ELSE ? END)',
                        [
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    $this->rightColumn => new Expression(
                        '(CASE WHEN ? > :rightDecreaseStart THEN ? - :rightDecrease ELSE ? END)',
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
                    new Expression(':start')
                );

            $this->statements['close_gap'] = $this->sql->prepareStatementForSqlObject($update);
        }

        /** @var StatementInterface $closeGapStatement */
        $closeGapStatement = $this->statements['close_gap'];

        $parameters = [
            ':leftDecreaseStart' => $range['rgt'],
            ':leftDecrease' => $size,
            ':rightDecreaseStart' => $range['rgt'],
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
     * Creates statement for creating gap when inserting nodes
     *
     * @return StatementInterface
     */
    protected function getInsertMethodCreateGapStatement()
    {
        /*
         * Create a gap to insert new record
         */
        if (! array_key_exists('create_gap__insert', $this->statements)) {
            $update = new Update($this->table);

            $update
                ->set([
                    $this->rightColumn => new Expression(
                        '? + 2',
                        [
                            [$this->rightColumn => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    $this->leftColumn => new Expression(
                        '(CASE WHEN ? > :newPosition THEN ? + 2 ELSE ? END)',
                        [
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER]
                        ]
                    )
                ])
                ->where
                ->greaterThanOrEqualTo(
                    $this->rightColumn,
                    new Expression(':newPositionWhere')
                );

            $this->statements['create_gap__insert'] = $this->sql->prepareStatementForSqlObject($update);
        }

        return $this->statements['create_gap__insert'];
    }

    /**
     * Creates a root node
     *
     * @return string Root node identifier
     * @throws RuntimeException
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
            throw new RuntimeException(
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

        if (! $result instanceof ResultInterface) {
            throw new UnknownDbException();
        }

        return $result->getGeneratedValue();
    }

    /**
     * Inserts new node with provided data
     *
     * @param int|string $parent Identifier of parent node
     * @param array      $data   Data for new node
     * @return mixed|null Identifier for newly created node
     * @throws InvalidNodeIdentifierException
     * @throws RuntimeException
     *
     * TODO: add ability to insert after or before a node, like in move method
     */
    public function insert($parent, array $data = [])
    {
        if (! is_int($parent) && ! is_string($parent)) {
            throw new InvalidNodeIdentifierException($parent, 'Parent');
        }

        /*
         * Get parents right column value as left column value for new node
         */
        $result = $this->findNodes([$parent]);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        if (0 === $result->getAffectedRows()) {
            throw new RuntimeException(sprintf(
                "Parent with identifier %s was not found or not unique",
                $parent
            ));
        }
        $newPosition = (int)$result->current()['rgt'];

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
        $data[$this->leftColumn] = $newPosition;
        $data[$this->rightColumn] = $newPosition + 1;

        $insert = new Insert($this->table);

        $insert->values($data);

        $result = $this->sql->prepareStatementForSqlObject($insert)->execute();

        if (! $result instanceof ResultInterface) {
            throw new UnknownDbException();
        }

        return $result->getGeneratedValue();
    }

    /**
     * Move node(s)
     *
     * @param int|string|array $source      Identifier of source node or array of identifiers
     * @param int|string       $destination Identifier of destination node
     * @param string           $position    Move node to before/after destination or make it a child of destination node
     * @return int                          Number of nodes moved
     * @throws InvalidArgumentException
     * @throws InvalidNodeIdentifierException
     * @throws NodeChildOrSiblingToItself
     */
    public function move($source, $destination, $position = self::MOVE_AFTER)
    {
        if (! is_int($source) && ! is_string($source) && ! is_array($source)) {
            // TODO: Exception does not state it can be array
            throw new InvalidNodeIdentifierException($source, 'Source node');
        }

        if (! is_int($destination) && ! is_string($destination)) {
            throw new InvalidNodeIdentifierException($destination, 'Destination node');
        }

        if ($position !== self::MOVE_AFTER && $position !== self::MOVE_BEFORE && $position !== self::MOVE_MAKE_CHILD) {
            throw new InvalidArgumentException(sprintf(
                '$where parameter value can be either \'after\', \'before\' or \'make_child\'. \'%s\' given',
                $position
            ));
        }

        /*
         * Prevent user from moving node to be sibling of root node
         */
        if ($this->getRootNodeId() == $destination
            && (self::MOVE_AFTER === $position || self::MOVE_BEFORE === $position)
        ) {
            throw new RuntimeException('Node can not be moved to be sibling of root node');
        }

        /*
         * Bail early if moving single node when source and destination are same
         */
        if (! is_array($source) && $source == $destination) {
            if ($position === self::MOVE_MAKE_CHILD) {
                throw new NodeChildOrSiblingToItself();
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
        if (! empty($source)) {
            $count += $this->move($source, $sourceRange['last_id'], self::MOVE_AFTER);
        }

        return (int)$count;
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
     * @param int|string|array $id Identifier of source node or array of identifiers
     * @return int                 Number of nodes deleted
     * @throws InvalidNodeIdentifierException
     * @throws RuntimeException
     */
    public function delete($id)
    {
        if (! is_int($id) && ! is_string($id) && ! is_array($id)) {
            // TODO: Exception does not state it can be array
            throw new InvalidNodeIdentifierException($id);
        }

        /*
         * Prevent user from deleting root node
         */
        if (is_array($id) && in_array($this->getRootNodeId(), $id)
            || $this->getRootNodeId() == $id
        ) {
            throw new RuntimeException('Root node can\'t be deleted');
        }

        $id = $this->sourcesToRange($id, false);

        $range = array_shift($id);

        $count = $this->deleteRange($range);

        if (! empty($id)) {
            $count += $this->delete($id);
        }

        return $count;
    }

    /**
     * Empties a node by removing its descendants
     * or by moving them to a new location
     *
     * @param int|string|array $parents     Identifier of parent node or array with identifiers
     *                                      if multiple parents are to be cleaned
     * @param null|int|string  $destination Identifier of destination node or null
     * @param string           $position    Move node to before/after destination or make it a child
     *                                      of destination node
     * @return int Number of affected rows
     * @throws InvalidArgumentException
     * @throws InvalidNodeIdentifierException
     * @throws RuntimeException
     */
    public function clean($parents, $destination = null, $position = self::MOVE_MAKE_CHILD)
    {
        if (! is_int($parents) && ! is_string($parents) && ! is_array($parents)) {
            // TODO: Exception does not state it can be array
            throw new InvalidNodeIdentifierException($parents);
        }

        if (! is_null($destination) && ! is_int($destination) && ! is_string($destination)) {
            throw new InvalidNodeIdentifierException($destination);
        }

        if (null !== $destination
            && $position !== self::MOVE_AFTER
            && $position !== self::MOVE_BEFORE
            && $position !== self::MOVE_MAKE_CHILD
        ) {
            throw new InvalidArgumentException(sprintf(
                '$where parameter value can be either \'after\', \'before\' or \'make_child\'. \'%s\' given',
                $position
            ));
        }

        if (! is_array($parents)) {
            $parents = [$parents];
        }

        $children = $this->findChildren($parents);

        if (! count($children)) {
            return 0;
        }

        if ($destination) {
            $count = $this->move($children, $destination, $position);
        } else {
            $count = $this->delete($children);
        }

        return $count;
    }
}
