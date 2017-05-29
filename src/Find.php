<?php

namespace metalinspired\NestedSet;

use metalinspired\NestedSet\Exception\InvalidArgumentException;
use metalinspired\NestedSet\Exception\InvalidNodeIdentifierException;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\Adapter\Driver\StatementInterface;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Join;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Sql\Select;

//TODO: Implement error checking in find methods
class Find extends AbstractNestedSet
{
    /**
     * Include searching node in results
     *
     * @var bool
     */
    protected $includeSearchingNode = false;

    /**
     * Columns to fetch
     *
     * @var array
     */
    protected $columns = [Select::SQL_STAR];

    /**
     * @var Join
     */
    protected $joins = [];

    /**
     * How deep results to return
     *
     * @var int|null
     */
    protected $depthLimit = null;

    /**
     * Find constructor
     *
     * @param Config $config Configuration object
     */
    public function __construct(Config $config = null)
    {
        parent::__construct($config);
        $this->joins = new Join();
    }

    /**
     * Returns currently set behavior for including searching node in results
     *
     * @return bool
     */
    public function getIncludeSearchingNode()
    {
        return $this->includeSearchingNode;
    }

    /**
     * Sets behavior for including searching node in results
     *
     * @param bool $includeSearchingNode
     * @return $this Provides a fluent interface
     */
    public function setIncludeSearchingNode($includeSearchingNode)
    {
        $this->statements = [];
        $this->includeSearchingNode = (bool)$includeSearchingNode;
        return $this;
    }

    /**
     * Returns columns to fetch in results
     *
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Sets columns to fetch in results
     *
     * @see Select::columns() For explanation of possible states
     * @param array $columns
     * @return $this Provides a fluent interface
     */
    public function setColumns(array $columns)
    {
        $this->statements = [];
        $this->columns = $columns;
        return $this;
    }

    /**
     * Create a join clause
     *
     * @see Select::join()
     * @param  string|array $name
     * @param  string       $on
     * @param  string|array $columns
     * @param  string       $type one of the JOIN_* constants
     * @return $this Provides a fluent interface
     */
    public function join($name, $on, $columns = [Select::SQL_STAR], $type = Join::JOIN_INNER)
    {
        $this->statements = [];
        $this->joins->join($name, $on, $columns, $type);
        return $this;
    }

    /**
     * Removes all join clauses
     *
     * @return $this Provides a fluent interface
     */
    public function resetJoins()
    {
        $this->statements = [];
        $this->joins->reset();
        return $this;
    }

    /**
     * Returns currently set depth limit
     *
     * @return int|null
     */
    public function getDepthLimit()
    {
        return $this->depthLimit;
    }

    /**
     * Sets how deep results to return
     *
     * @param int|null $depthLimit
     * @return $this Provides a fluent interface
     * @throws InvalidArgumentException
     */
    public function setDepthLimit($depthLimit)
    {
        if (!is_int($depthLimit) && null !== $depthLimit) {
            throw new InvalidArgumentException();
        }

        $this->statements = [];
        $this->depthLimit = $depthLimit;
        return $this;
    }

    /**
     * Builds a query that fetches ancestors
     *
     * @param null|int $depthLimit
     * @return Select
     */
    protected function getFindAncestorsQuery($depthLimit = null)
    {
        $subSelect = new Select($this->getTable());

        $subSelect
            ->columns([
                'lft' => $this->leftColumn,
                'rgt' => $this->rightColumn
            ], false)
            ->where
            ->equalTo($this->idColumn, new Expression(':id'));

        $select = new Select(['q' => $subSelect]);

        $joinOn = '? <' . ($this->includeSearchingNode ? '=' : '') . ' ?' .
            ' AND ? >' . ($this->includeSearchingNode ? '=' : '') . ' ?' .
            ' AND ? > ?';

        $joinOn = new Expression(
            $joinOn,
            [
                ["t.{$this->leftColumn}" => Expression::TYPE_IDENTIFIER],
                ['q.lft' => Expression::TYPE_IDENTIFIER],
                ["t.{$this->rightColumn}" => Expression::TYPE_IDENTIFIER],
                ['q.rgt' => Expression::TYPE_IDENTIFIER],
                ["t.{$this->idColumn}" => Expression::TYPE_IDENTIFIER],
                $this->rootNodeId
            ]
        );

        $select
            ->columns([])
            ->join(
                ['t' => $this->table],
                $joinOn,
                $this->columns
            )
            ->order(
                new Expression(
                    '? DESC',
                    [
                        ["t.{$this->leftColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                )
            );

        if ($depthLimit || $this->depthLimit) {
            $depthLimit = $depthLimit ? $depthLimit : $this->depthLimit;
            if ($this->includeSearchingNode) {
                $depthLimit++;
            }
            $select->limit($depthLimit);
        }

        foreach ($this->joins as $join) {
            $select->join($join['name'], $join['on'], $join['columns'], $join['type']);
        }

        return $select;
    }

    /**
     * Builds a query to fetch descendants
     *
     * @param null|int $depthLimit
     * @return Select
     */
    protected function getFindDescendantsQuery($depthLimit = null)
    {
        $subSelect = new Select(['head_parent' => $this->table]);

        $subSelect
            ->columns([])
            ->join(
                ['parent' => $this->table],
                new Expression(
                    '? >= ? AND ? < ?',
                    [
                        ["parent.{$this->leftColumn}" => Expression::TYPE_IDENTIFIER],
                        ["head_parent.{$this->leftColumn}" => Expression::TYPE_IDENTIFIER],
                        ["parent.{$this->rightColumn}" => Expression::TYPE_IDENTIFIER],
                        ["head_parent.{$this->rightColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                []
            )
            ->join(
                ['child' => $this->table],
                new Expression(
                    '? BETWEEN ? AND ?',
                    [
                        ["child.{$this->leftColumn}" => Expression::TYPE_IDENTIFIER],
                        ["parent.{$this->leftColumn}" => Expression::TYPE_IDENTIFIER],
                        ["parent.{$this->rightColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                [
                    'id' => new Expression(
                        '?',
                        [
                            ["child.{$this->idColumn}" => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    'depth' => new Expression(
                        '(CASE WHEN ? = :childDepthId THEN 0 ELSE COUNT(*) END)',
                        [
                            ["child.{$this->idColumn}" => Expression::TYPE_IDENTIFIER]
                        ]
                    )
                ]
            )
            ->group(
                new Expression(
                    '?',
                    [
                        ["child.{$this->idColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                )
            );

        $subSelect->having
            ->greaterThanOrEqualTo(new Expression('COUNT(*)'), 1);

        if ($depthLimit || $this->depthLimit) {
            $depthLimit = $depthLimit ? $depthLimit : $this->depthLimit;
            $subSelect->having
                ->lessThanOrEqualTo(new Expression('COUNT(*)'), $depthLimit);
        }

        $subSelect->where
            ->equalTo(
                new Expression(
                    '?',
                    [
                        ["head_parent.{$this->idColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                new Expression(':id')
            )
            ->greaterThan(
                new Expression(
                    '?',
                    [
                        ["parent.{$this->idColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                $this->rootNodeId
            );

        if ($this->includeSearchingNode) {
            $subSelect
                ->where
                ->or
                ->equalTo(
                    new Expression(
                        '?',
                        [
                            ["child.{$this->idColumn}" => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    new Expression(':searchNodeId'));
        }

        $select = new Select(['q' => $subSelect]);

        $select
            ->columns(['depth'])
            ->join(
                ['t' => $this->table],
                new Expression(
                    '? = ?',
                    [
                        ["t.{$this->idColumn}" => Expression::TYPE_IDENTIFIER],
                        ["q.{$this->idColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                $this->columns
            )
            ->order(new Expression(
                    '? ASC',
                    [
                        ["t.{$this->leftColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                )
            );

        foreach ($this->joins as $join) {
            $select->join($join['name'], $join['on'], $join['columns'], $join['type']);
        }

        return $select;
    }

    /**
     * Builds a query to fetch first/last child
     *
     * @param bool $last
     * @return Select
     */
    protected function getFindChildQuery($last = false)
    {
        $select = new Select(['t' => $this->table]);

        $select
            ->columns($this->columns)
            ->join(
                ['q' => $this->table],
                new Expression(
                    '? = :id',
                    [
                        ["q.{$this->idColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                []
            )
            ->order(
                new Expression(
                    '?',
                    [
                        ["q.{$this->leftColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                )
            )
            ->where
            ->greaterThan(
                new Expression(
                    '?',
                    [
                        ["t.{$this->idColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                $this->rootNodeId
            );

        $predicate = new Predicate();

        $column = $last ? $this->rightColumn : $this->leftColumn;

        $predicate->equalTo(
            new Expression(
                '?',
                [
                    ["t.{$column}" => Expression::TYPE_IDENTIFIER]
                ]
            ),
            new Expression(
                '?' . ($last ? '-' : '+') . '1',
                [
                    ["q.{$column}" => Expression::TYPE_IDENTIFIER]
                ]
            )
        );

        if ($this->includeSearchingNode) {
            $predicate
                ->or
                ->equalTo(
                    new Expression(
                        '?',
                        [
                            ["t.{$this->idColumn}" => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    new Expression(':includeId')
                );
        }

        $select
            ->where
            ->nest()
            ->addPredicate($predicate);

        return $select;
    }

    /**
     * Builds a query to fetch siblings
     *
     * @return Select
     */
    protected function getFindSiblingsQuery()
    {
        $subSelectSelect = new Select(['parent' => $this->table]);

        $subSelectSelect
            ->columns([
                $this->leftColumn,
                $this->rightColumn
            ])
            ->join(
                ['node' => $this->table],
                new Expression(
                    '? = :id',
                    [
                        ["node.{$this->idColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                []
            )
            ->order(
                new Expression(
                    '? DESC',
                    [
                        ["parent.{$this->leftColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                )
            )
            ->limit(1)
            ->where
            ->greaterThan(
                new Expression(
                    '?',
                    [
                        ["node.{$this->leftColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                new Expression(
                    '?',
                    [
                        ["parent.{$this->leftColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                )
            )
            ->lessThan(
                new Expression(
                    '?',
                    [
                        ["node.{$this->rightColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                new Expression(
                    '?',
                    [
                        ["parent.{$this->rightColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                )
            );

        $subSelect = new Select(['head_parent' => $subSelectSelect]);

        $subSelect
            ->columns([])
            ->join(
                ['parent' => $this->table],
                new Expression(
                    '? >= ? AND ? < ?',
                    [
                        ["parent.{$this->leftColumn}" => Expression::TYPE_IDENTIFIER],
                        ["head_parent.{$this->leftColumn}" => Expression::TYPE_IDENTIFIER],
                        ["parent.{$this->rightColumn}" => Expression::TYPE_IDENTIFIER],
                        ["head_parent.{$this->rightColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                []
            )
            ->join(
                ['child' => $this->table],
                new Expression(
                    '? BETWEEN ? AND ?',
                    [
                        ["child.{$this->leftColumn}" => Expression::TYPE_IDENTIFIER],
                        ["parent.{$this->leftColumn}" => Expression::TYPE_IDENTIFIER],
                        ["parent.{$this->rightColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                [$this->idColumn]
            )
            ->group(
                new Expression(
                    '?',
                    [
                        ["child.{$this->idColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                )
            );

        $subSelect
            ->having
            ->equalTo(
                new Expression('COUNT(*)'),
                1
            );

        if (!$this->includeSearchingNode) {
            $subSelect
                ->where
                ->notEqualTo(
                    new Expression(
                        '?',
                        [
                            ["child.{$this->idColumn}" => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    new Expression(':childId')
                );
        }

        $select = new Select(['q' => $subSelect]);

        $select
            ->columns([])
            ->join(
                ['t' => $this->table],
                new Expression(
                    '? = ?',
                    [
                        ["t.{$this->idColumn}" => Expression::TYPE_IDENTIFIER],
                        ["q.{$this->idColumn}" => Expression::TYPE_IDENTIFIER],
                    ]
                ),
                $this->columns
            )
            ->order(
                new Expression(
                    '? ASC',
                    [
                        ["t.{$this->leftColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                )
            );

        return $select;
    }

    /**
     * Builds a query to fetch next/previous sibling
     *
     * @param bool $previous
     * @return Select
     */
    protected function getFindSiblingQuery($previous = false)
    {
        $select = new Select(['t' => $this->table]);

        $select
            ->columns($this->columns)
            ->join(
                ['q' => $this->table],
                new Expression(
                    '? = :id',
                    [
                        ["q.{$this->idColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                []
            )
            ->order(
                new Expression(
                    '? ' . ($previous ? 'DESC' : 'ASC'),
                    [
                        ["t.{$this->leftColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                )
            )
            ->where
            ->greaterThan(
                new Expression(
                    '?',
                    [
                        ["t.{$this->idColumn}" => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                $this->rootNodeId
            );

        $predicate = new Predicate();

        $column1 = $previous ? $this->rightColumn : $this->leftColumn;
        $column2 = $previous ? $this->leftColumn : $this->rightColumn;

        $predicate->equalTo(
            new Expression(
                '?',
                [
                    ["t.{$column1}" => Expression::TYPE_IDENTIFIER]
                ]
            ),
            new Expression(
                '? ' . ($previous ? '-' : '+') . ' 1',
                [
                    ["q.{$column2}" => Expression::TYPE_IDENTIFIER]
                ]
            )
        );

        if ($this->includeSearchingNode) {
            $predicate
                ->or
                ->equalTo(
                    new Expression(
                        '?',
                        [
                            ["t.{$this->idColumn}" => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    new Expression(':includeId')
                );
        }

        $select
            ->where
            ->addPredicate($predicate);

        return $select;
    }

    /**
     * Finds ancestors of a node
     *
     * @param mixed $id Node identifier
     * @return ResultInterface
     * @throws InvalidNodeIdentifierException
     */
    public function findAncestors($id)
    {
        if (!is_int($id) && !is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (!array_key_exists('find_ancestors', $this->statements)) {
            $this->statements['find_ancestors'] = $this->sql->prepareStatementForSqlObject(
                $this->getFindAncestorsQuery()
            );
        }

        $parameters = [':id' => $id];

        /** @var StatementInterface $statement */
        $statement = $this->statements['find_ancestors'];

        return $statement->execute($parameters);
    }

    /**
     * Finds a parent (first ancestor) of a node
     *
     * @param mixed $id Node identifier
     * @return ResultInterface
     * @throws InvalidNodeIdentifierException
     */
    public function findParent($id)
    {
        if (!is_int($id) && !is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (!array_key_exists('find_parent', $this->statements)) {
            $this->statements['find_parent'] = $this->sql->prepareStatementForSqlObject(
                $this->getFindAncestorsQuery(1)
            );
        }

        $parameters = [':id' => $id];

        /** @var StatementInterface $statement */
        $statement = $this->statements['find_parent'];

        return $statement->execute($parameters);
    }

    /**
     * Find descendants of a node
     *
     * @param mixed $id Node identifier
     * @return ResultInterface
     * @throws InvalidNodeIdentifierException
     */
    public function findDescendants($id)
    {
        if (!is_int($id) && !is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (!array_key_exists('find_descendants', $this->statements)) {
            $this->statements['find_descendants'] = $this->sql->prepareStatementForSqlObject(
                $this->getFindDescendantsQuery()
            );
        }

        $parameters = [
            ':id' => $id,
            ':childDepthId' => $id
        ];

        if ($this->includeSearchingNode) {
            $parameters[':searchNodeId'] = $id;
        }

        /** @var StatementInterface $statement */
        $statement = $this->statements['find_descendants'];

        return $statement->execute($parameters);
    }

    /**
     * Finds children (direct descendants) of a node
     *
     * @param mixed $id Node identifier
     * @return ResultInterface
     * @throws InvalidNodeIdentifierException
     */
    public function findChildren($id)
    {
        if (!is_int($id) && !is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (!array_key_exists('find_children', $this->statements)) {
            $this->statements['find_children'] = $this->sql->prepareStatementForSqlObject(
                $this->getFindDescendantsQuery(1)
            );
        }

        $parameters = [
            ':id' => $id,
            ':childDepthId' => $id
        ];

        if ($this->includeSearchingNode) {
            $parameters[':searchNodeId'] = $id;
        }

        /** @var StatementInterface $statement */
        $statement = $this->statements['find_children'];

        return $statement->execute($parameters);
    }

    /**
     * Finds first child of a node
     *
     * @param mixed $id Node identifier
     * @return ResultInterface
     * @throws InvalidNodeIdentifierException
     */
    public function findFirstChild($id)
    {
        if (!is_int($id) && !is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (!array_key_exists('find_first_child', $this->statements)) {
            $this->statements['find_first_child'] = $this->sql->prepareStatementForSqlObject(
                $this->getFindChildQuery()
            );
        }

        $parameters = [':id' => $id];

        if ($this->includeSearchingNode) {
            $parameters[':includeId'] = $id;
        }

        /** @var StatementInterface $statement */
        $statement = $this->statements['find_first_child'];

        return $statement->execute($parameters);
    }

    /**
     * Finds last child of a node
     *
     * @param mixed $id Node identifier
     * @return ResultInterface
     * @throws InvalidNodeIdentifierException
     */
    public function findLastChild($id)
    {
        if (!is_int($id) && !is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (!array_key_exists('find_last_child', $this->statements)) {
            $this->statements['find_last_child'] = $this->sql->prepareStatementForSqlObject(
                $this->getFindChildQuery(true)
            );
        }

        $parameters = [':id' => $id];

        if ($this->includeSearchingNode) {
            $parameters[':includeId'] = $id;
        }

        /** @var StatementInterface $statement */
        $statement = $this->statements['find_last_child'];

        return $statement->execute($parameters);
    }

    /**
     * Finds sibling of a node
     *
     * @param mixed $id Node identifier
     * @return ResultInterface
     * @throws InvalidNodeIdentifierException
     */
    public function findSiblings($id)
    {
        if (!is_int($id) && !is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (!array_key_exists('find_siblings', $this->statements)) {
            $this->statements['find_siblings'] = $this->sql->prepareStatementForSqlObject(
                $this->getFindSiblingsQuery()
            );
        }

        $parameters = [':id' => $id];

        if (!$this->includeSearchingNode) {
            $parameters[':childId'] = $id;
        }

        /** @var StatementInterface $statement */
        $statement = $this->statements['find_siblings'];

        return $statement->execute($parameters);
    }

    /**
     * Finds next sibling of a node
     *
     * @param mixed $id Node identifier
     * @return ResultInterface
     * @throws InvalidNodeIdentifierException
     */
    public function findNextSibling($id)
    {
        if (!is_int($id) && !is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (!array_key_exists('find_next_sibling', $this->statements)) {
            $this->statements['find_next_sibling'] = $this->sql->prepareStatementForSqlObject(
                $this->getFindSiblingQuery()
            );
        }

        $parameters = [':id' => $id];

        if ($this->includeSearchingNode) {
            $parameters[':includeId'] = $id;
        }

        /** @var StatementInterface $statement */
        $statement = $this->statements['find_next_sibling'];

        return $statement->execute($parameters);
    }

    /**
     * Finds previous sibling of a node
     *
     * @param mixed $id Node identifier
     * @return ResultInterface
     * @throws InvalidNodeIdentifierException
     */
    public function findPreviousSibling($id)
    {
        if (!is_int($id) && !is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (!array_key_exists('find_previous_sibling', $this->statements)) {
            $this->statements['find_previous_sibling'] = $this->sql->prepareStatementForSqlObject(
                $this->getFindSiblingQuery(true)
            );
        }

        $parameters = [':id' => $id];

        if ($this->includeSearchingNode) {
            $parameters[':includeId'] = $id;
        }

        /** @var StatementInterface $statement */
        $statement = $this->statements['find_previous_sibling'];

        return $statement->execute($parameters);
    }
}
