<?php

namespace metalinspired\NestedSet;

use metalinspired\NestedSet\Exception\InvalidArgumentException;
use metalinspired\NestedSet\Exception\InvalidNodeIdentifierException;
use metalinspired\NestedSet\Exception\UnknownDbException;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\Adapter\Driver\StatementInterface;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Join;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Sql\Select;

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
        if (! is_int($depthLimit) && null !== $depthLimit) {
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
            ->equalTo(
                $this->idColumn,
                new Expression(':id')
            );

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
                $this->getRootNodeId()
            ]
        );

        $select
            ->columns([])
            ->join(
                ['t' => $this->table],
                $joinOn,
                $this->columns
            )
            ->order("t.{$this->leftColumn} DESC");

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
                "parent.{$this->leftColumn} >= head_parent.{$this->leftColumn} " .
                "AND parent.{$this->rightColumn} < head_parent.{$this->rightColumn}",
                []
            )
            ->join(
                ['child' => $this->table],
                "child.{$this->leftColumn} BETWEEN parent.{$this->leftColumn} AND parent.{$this->rightColumn}",
                [
                    'id' => $this->idColumn,
                    'depth' => new Expression(
                        '(CASE WHEN ? = :childDepthId THEN 0 ELSE COUNT(*) END)',
                        [
                            ["child.{$this->idColumn}" => Expression::TYPE_IDENTIFIER]
                        ]
                    )
                ]
            )
            ->group("child.{$this->idColumn}");

        $subSelect->having
            ->greaterThanOrEqualTo(new Expression('COUNT(*)'), 1);

        if ($depthLimit || $this->depthLimit) {
            $depthLimit = $depthLimit ? $depthLimit : $this->depthLimit;
            $subSelect->having
                ->lessThanOrEqualTo(new Expression('COUNT(*)'), $depthLimit);
        }

        $subSelect->where
            ->equalTo(
                "head_parent.{$this->idColumn}",
                new Expression(':id')
            )
            ->greaterThan(
                "parent.{$this->idColumn}",
                $this->getRootNodeId()
            );

        if ($this->includeSearchingNode) {
            $subSelect
                ->where
                ->or
                ->equalTo(
                    "child.{$this->idColumn}",
                    new Expression(':searchNodeId')
                );
        }

        $select = new Select(['q' => $subSelect]);

        $select
            ->columns(['depth'])
            ->join(
                ['t' => $this->table],
                "t.{$this->idColumn} = q.{$this->idColumn}",
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
        $column = $last ? $this->rightColumn : $this->leftColumn;

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
            ->order("t.{$this->leftColumn}")
            ->where
            ->equalTo(
                "t.{$column}",
                new Expression(
                    '? ' . ($last ? '-' : '+') . ' 1',
                    [
                        ["q.{$column}" => Expression::TYPE_IDENTIFIER]
                    ]
                )
            );

        if ($this->includeSearchingNode) {
            $select
                ->where
                ->or
                ->equalTo(
                    "t.{$this->idColumn}",
                    new Expression(':sId')
                );
        }


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
            ->order("parent.{$this->leftColumn} DESC")
            ->limit(1)
            ->where
            ->greaterThan(
                "node.{$this->leftColumn}",
                "parent.{$this->leftColumn}",
                Predicate::TYPE_IDENTIFIER,
                Predicate::TYPE_IDENTIFIER
            )
            ->lessThan(
                "node.{$this->rightColumn}",
                "parent.{$this->rightColumn}",
                Predicate::TYPE_IDENTIFIER,
                Predicate::TYPE_IDENTIFIER
            );

        $subSelect = new Select(['head_parent' => $subSelectSelect]);

        $subSelect
            ->columns([])
            ->join(
                ['parent' => $this->table],
                "parent.{$this->leftColumn} >= head_parent.{$this->leftColumn} " .
                "AND parent.{$this->rightColumn} < head_parent.{$this->rightColumn}",
                []
            )
            ->join(
                ['child' => $this->table],
                "child.{$this->leftColumn} BETWEEN parent.{$this->leftColumn} AND parent.{$this->rightColumn}",
                [$this->idColumn]
            )
            ->group("child.{$this->idColumn}");

        $subSelect
            ->having
            ->equalTo(
                new Expression('COUNT(*)'),
                1
            );

        if (! $this->includeSearchingNode) {
            $subSelect
                ->where
                ->notEqualTo(
                    "child.{$this->idColumn}",
                    new Expression(':childId')
                );
        }

        $select = new Select(['q' => $subSelect]);

        $select
            ->columns([])
            ->join(
                ['t' => $this->table],
                "t.{$this->idColumn} = q.{$this->idColumn}",
                $this->columns
            )
            ->order("t.{$this->leftColumn} ASC");

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
            ->order("t.{$this->leftColumn} " . ($previous ? 'DESC' : 'ASC'))
            ->where
            ->greaterThan(
                "t.{$this->idColumn}",
                5
            );

        $predicate = new Predicate();

        $column1 = $previous ? $this->rightColumn : $this->leftColumn;
        $column2 = $previous ? $this->leftColumn : $this->rightColumn;

        $predicate->equalTo(
            "t.{$column1}",
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
                    "t.{$this->idColumn}",
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
        if (! is_int($id) && ! is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (! array_key_exists('find_ancestors', $this->statements)) {
            $this->statements['find_ancestors'] = $this->sql->prepareStatementForSqlObject(
                $this->getFindAncestorsQuery()
            );
        }

        $parameters = [':id' => $id];

        /** @var StatementInterface $statement */
        $statement = $this->statements['find_ancestors'];

        $result = $statement->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
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
        if (! is_int($id) && ! is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (! array_key_exists('find_parent', $this->statements)) {
            $this->statements['find_parent'] = $this->sql->prepareStatementForSqlObject(
                $this->getFindAncestorsQuery(1)
            );
        }

        $parameters = [':id' => $id];

        /** @var StatementInterface $statement */
        $statement = $this->statements['find_parent'];

        $result = $statement->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
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
        if (! is_int($id) && ! is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (! array_key_exists('find_descendants', $this->statements)) {
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

        $result = $statement->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
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
        if (! is_int($id) && ! is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (! array_key_exists('find_children', $this->statements)) {
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

        $result = $statement->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
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
        if (! is_int($id) && ! is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (! array_key_exists('find_first_child', $this->statements)) {
            $this->statements['find_first_child'] = $this->sql->prepareStatementForSqlObject(
                $this->getFindChildQuery()
            );
        }

        $parameters = [':id' => $id];

        if ($this->includeSearchingNode) {
            $parameters[':sId'] = $id;
        }

        /** @var StatementInterface $statement */
        $statement = $this->statements['find_first_child'];

        $result = $statement->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
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
        if (! is_int($id) && ! is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (! array_key_exists('find_last_child', $this->statements)) {
            $this->statements['find_last_child'] = $this->sql->prepareStatementForSqlObject(
                $this->getFindChildQuery(true)
            );
        }

        $parameters = [':id' => $id];

        if ($this->includeSearchingNode) {
            $parameters[':sId'] = $id;
        }

        /** @var StatementInterface $statement */
        $statement = $this->statements['find_last_child'];

        $result = $statement->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
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
        if (! is_int($id) && ! is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (! array_key_exists('find_siblings', $this->statements)) {
            $this->statements['find_siblings'] = $this->sql->prepareStatementForSqlObject(
                $this->getFindSiblingsQuery()
            );
        }

        $parameters = [':id' => $id];

        if (! $this->includeSearchingNode) {
            $parameters[':childId'] = $id;
        }

        /** @var StatementInterface $statement */
        $statement = $this->statements['find_siblings'];

        $result = $statement->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
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
        if (! is_int($id) && ! is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (! array_key_exists('find_next_sibling', $this->statements)) {
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

        $result = $statement->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
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
        if (! is_int($id) && ! is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (! array_key_exists('find_previous_sibling', $this->statements)) {
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

        $result = $statement->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
    }
}
