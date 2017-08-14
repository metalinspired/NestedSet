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

use metalinspired\NestedSet\Exception\InvalidArgumentException;
use metalinspired\NestedSet\Exception\InvalidNodeIdentifierException;
use metalinspired\NestedSet\Exception\UnknownDbException;
use Zend\Db\Adapter\Driver\ResultInterface;
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
     * @param array|null        $columns
     * @param array|string|null $order
     * @param Predicate|null    $where
     * @param int|null          $depthLimit
     * @param bool|null         $includeSearchingNode
     * @return Select
     */
    public function getFindAncestorsQuery(
        $columns = null,
        $order = null,
        Predicate $where = null,
        $depthLimit = null,
        $includeSearchingNode = null
    ) {
        $includeSearchingNode = is_null($includeSearchingNode) ? $this->includeSearchingNode : $includeSearchingNode;

        $subSelect = new Select($this->getTable());

        $subSelect
            ->columns([
                'lft' => $this->leftColumn,
                'rgt' => $this->rightColumn,
            ], false)
            ->where
            ->equalTo(
                $this->idColumn,
                new Expression(':id')
            );

        $select = new Select(['q' => $subSelect]);

        $joinOn = '? <' . ($includeSearchingNode ? '=' : '') . ' ?' .
            ' AND ? >' . ($includeSearchingNode ? '=' : '') . ' ?' .
            ' AND ? > ?';

        $joinOn = new Expression(
            $joinOn,
            [
                ["t.{$this->leftColumn}" => Expression::TYPE_IDENTIFIER],
                ['q.lft' => Expression::TYPE_IDENTIFIER],
                ["t.{$this->rightColumn}" => Expression::TYPE_IDENTIFIER],
                ['q.rgt' => Expression::TYPE_IDENTIFIER],
                ["t.{$this->idColumn}" => Expression::TYPE_IDENTIFIER],
                $this->getRootNodeId(),
            ]
        );

        $select
            ->columns([])
            ->join(
                ['t' => $this->table],
                $joinOn,
                $columns ? $columns : $this->columns
            )
            ->order($order ? $order : "t.{$this->leftColumn} DESC");

        if ($depthLimit || $this->depthLimit) {
            $depthLimit = $depthLimit ? $depthLimit : $this->depthLimit;
            if ($includeSearchingNode) {
                $depthLimit++;
            }
            $select->limit($depthLimit);
        }

        foreach ($this->joins as $join) {
            $select->join($join['name'], $join['on'], $join['columns'], $join['type']);
        }

        if ($where) {
            $select
                ->where
                ->predicate($where);
        }

        return $select;
    }

    /**
     * @param array|null $columns
     * @param bool|null  $includeSearchingNode
     * @return Select
     */
    public function getFindParentQuery($columns = null, $includeSearchingNode = null)
    {
        return $this->getFindAncestorsQuery($columns, null, null, 1, $includeSearchingNode);
    }

    /**
     * Builds a query to fetch descendants
     *
     * @param array|null        $columns
     * @param array|string|null $order
     * @param Predicate|null    $where
     * @param int|null          $depthLimit
     * @param bool|null         $includeSearchingNode
     * @return Select
     */
    public function getFindDescendantsQuery(
        $columns = null,
        $order = null,
        Predicate $where = null,
        $depthLimit = null,
        $includeSearchingNode = null
    ) {
        $includeSearchingNode = is_null($includeSearchingNode) ? $this->includeSearchingNode : $includeSearchingNode;

        $subSelect = new Select(['head_parent' => $this->table]);

        $subSelect
            ->columns([])
            ->join(
                ['parent' => $this->table],
                "parent.{$this->leftColumn} >= head_parent.{$this->leftColumn} " .
                "AND parent.{$this->rightColumn} <" .
                ($includeSearchingNode ? '=' : '') .
                "head_parent.{$this->rightColumn}",
                []
            )
            ->join(
                ['child' => $this->table],
                "child.{$this->leftColumn} BETWEEN parent.{$this->leftColumn} AND parent.{$this->rightColumn}",
                [
                    'id' => $this->idColumn,
                    'depth' => new Expression(
                        '(CASE WHEN ? = ? THEN 1 ELSE COUNT(*) END)' . ($includeSearchingNode ? '-1' : ''),
                        [
                            ["child.{$this->idColumn}" => Expression::TYPE_IDENTIFIER],
                            ["head_parent.{$this->idColumn}" => Expression::TYPE_IDENTIFIER],
                        ]
                    ),
                ]
            )
            ->group([
                "child.{$this->idColumn}",
                "head_parent.{$this->idColumn}",
            ]);

        $depthLimit = $depthLimit ? $depthLimit : $this->depthLimit;

        if ($depthLimit) {
            if ($includeSearchingNode) {
                $depthLimit++;
            }
            $subSelect->having
                ->lessThanOrEqualTo(new Expression('COUNT(*)'), $depthLimit);
        } else {
            $subSelect->having
                ->greaterThanOrEqualTo(new Expression('COUNT(*)'), 1);
        }

        $subSelect->where
            ->equalTo(
                "head_parent.{$this->idColumn}",
                new Expression(':id')
            );

        $select = new Select(['q' => $subSelect]);

        $select
            ->columns(['depth'])
            ->join(
                ['t' => $this->table],
                "t.{$this->idColumn} = q.{$this->idColumn}",
                $columns ? $columns : $this->columns
            )
            ->order($order ? $order : "t.{$this->leftColumn} ASC")
            ->where
            ->greaterThan(
                "t.{$this->idColumn}",
                $this->getRootNodeId()
            );

        foreach ($this->joins as $join) {
            $select->join($join['name'], $join['on'], $join['columns'], $join['type']);
        }

        if ($where) {
            $select
                ->where
                ->predicate($where);
        }

        return $select;
    }

    /**
     * @param array|null        $columns
     * @param array|string|null $order
     * @param Predicate|null    $where
     * @param bool|null         $includeSearchingNode
     * @return Select
     */
    public function getFindChildrenQuery(
        $columns = null,
        $order = null,
        Predicate $where = null,
        $includeSearchingNode = null
    ) {
        return $this->getFindDescendantsQuery($columns, $order, $where, 1, $includeSearchingNode);
    }

    /**
     * Builds a query to fetch first/last child
     *
     * @param bool       $last
     * @param array|null $columns
     * @param bool|null  $includeSearchingNode
     * @return Select
     */
    protected function getFindChildQuery(
        $last,
        $columns,
        $includeSearchingNode
    ) {
        $includeSearchingNode = is_null($includeSearchingNode) ? $this->includeSearchingNode : $includeSearchingNode;

        $column = $last ? $this->rightColumn : $this->leftColumn;

        $select = new Select(['t' => $this->table]);

        $select
            ->columns($columns ? $columns : $this->columns)
            ->join(
                ['q' => $this->table],
                new Expression(
                    '? = :id',
                    [
                        ["q.{$this->idColumn}" => Expression::TYPE_IDENTIFIER],
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
                        ["q.{$column}" => Expression::TYPE_IDENTIFIER],
                    ]
                )
            );

        if ($includeSearchingNode) {
            $select
                ->where
                ->or
                ->equalTo(
                    "t.{$this->idColumn}",
                    "q.{$this->idColumn}",
                    Predicate::TYPE_IDENTIFIER,
                    Predicate::TYPE_IDENTIFIER
                );
        }


        return $select;
    }

    /**
     * Builds a query to fetch first child of a node
     *
     * @param array|null $columns
     * @param bool|null  $includeSearchingNode
     * @return Select
     */
    public function getFindFirstChildQuery($columns = null, $includeSearchingNode = null)
    {
        return $this->getFindChildQuery(false, $columns, $includeSearchingNode);
    }


    /**
     * Builds a query to fetch last child of a node
     *
     * @param array|null $columns
     * @param bool|null  $includeSearchingNode
     * @return Select
     */
    public function getFindLastChildQuery($columns = null, $includeSearchingNode = null)
    {
        return $this->getFindChildQuery(true, $columns, $includeSearchingNode);
    }

    /**
     * Builds a query to fetch siblings
     *
     * @param array|null        $columns
     * @param array|string|null $order
     * @param Predicate|null    $where
     * @param bool|null         $includeSearchingNode
     * @return Select
     */
    public function getFindSiblingsQuery(
        $columns = null,
        $order = null,
        Predicate $where = null,
        $includeSearchingNode = null
    ) {
        $includeSearchingNode = is_null($includeSearchingNode) ? $this->includeSearchingNode : $includeSearchingNode;

        $subSelectSelect = new Select(['parent' => $this->table]);

        $subSelectSelect
            ->columns([
                'lft' => "parent.{$this->leftColumn}",
                'rgt' => "parent.{$this->rightColumn}",
                'node_id' => "node.{$this->idColumn}",
            ], false)
            ->join(
                ['node' => $this->table],
                new Expression(
                    '? = :id',
                    [
                        ["node.{$this->idColumn}" => Expression::TYPE_IDENTIFIER],
                    ]
                ),
                []
            )
            ->order("parent.{$this->leftColumn} DESC")
            ->limit(1)
            ->where
            ->greaterThan(
                "node.{$this->leftColumn}",
                "parent.lft",
                Predicate::TYPE_IDENTIFIER,
                Predicate::TYPE_IDENTIFIER
            )
            ->lessThan(
                "node.{$this->rightColumn}",
                "parent.rgt",
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

        if (! $includeSearchingNode) {
            $subSelect
                ->where
                ->notEqualTo(
                    "child.{$this->idColumn}",
                    "head_parent.node_id",
                    Predicate::TYPE_IDENTIFIER,
                    Predicate::TYPE_IDENTIFIER
                );
        }

        $select = new Select(['q' => $subSelect]);

        $select
            ->columns([])
            ->join(
                ['t' => $this->table],
                "t.{$this->idColumn} = q.{$this->idColumn}",
                $columns ? $columns : $this->columns
            )
            ->order($order ? $order : "t.{$this->leftColumn} ASC");

        if ($where) {
            $select
                ->where
                ->predicate($where);
        }

        return $select;
    }

    /**
     * Builds a query to fetch next/previous sibling
     *
     * @param bool       $previous
     * @param array|null $columns
     * @param bool|null  $includeSearchingNode
     * @return Select
     */
    protected function getFindSiblingQuery(
        $previous,
        $columns,
        $includeSearchingNode
    ) {
        $includeSearchingNode = is_null($includeSearchingNode) ? $this->includeSearchingNode : $includeSearchingNode;

        $select = new Select(['t' => $this->table]);

        $select
            ->columns($columns ? $columns : $this->columns)
            ->join(
                ['q' => $this->table],
                new Expression(
                    '? = :id',
                    [
                        ["q.{$this->idColumn}" => Expression::TYPE_IDENTIFIER],
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
                    ["q.{$column2}" => Expression::TYPE_IDENTIFIER],
                ]
            )
        );

        if ($includeSearchingNode) {
            $predicate
                ->or
                ->equalTo(
                    "t.{$this->idColumn}",
                    "q.{$this->idColumn}",
                    Predicate::TYPE_IDENTIFIER,
                    Predicate::TYPE_IDENTIFIER
                );
        }

        $select
            ->where
            ->addPredicate($predicate);

        return $select;
    }

    /**
     * Builds a query to fetch next sibling of node
     *
     * @param array|null $columns
     * @param bool|null  $includeSearchingNode
     * @return Select
     */
    public function getFindNextSiblingQuery($columns = null, $includeSearchingNode = null)
    {
        return $this->getFindSiblingQuery(false, $columns, $includeSearchingNode);
    }


    /**
     * Builds a query to fetch previous sibling of node
     *
     * @param array|null $columns
     * @param bool|null  $includeSearchingNode
     * @return Select
     */
    public function getFindPreviousSiblingQuery($columns = null, $includeSearchingNode = null)
    {
        return $this->getFindSiblingQuery(true, $columns, $includeSearchingNode);
    }

    /**
     * Finds ancestors of a node
     *
     * @param mixed             $id Node identifier
     * @param array|null        $columns
     * @param array|string|null $order
     * @param Predicate|null    $where
     * @param int|null          $depthLimit
     * @param bool|null         $includeSearchingNode
     * @return ResultInterface
     * @throws InvalidNodeIdentifierException
     * @throws UnknownDbException
     */
    public function findAncestors(
        $id,
        $columns = null,
        $order = null,
        Predicate $where = null,
        $depthLimit = null,
        $includeSearchingNode = null
    ) {
        if (! is_int($id) && ! is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $query = $this->getFindAncestorsQuery($columns, $order, $where, $depthLimit, $includeSearchingNode);

        $parameters = [':id' => $id];

        $result = $this->sql->prepareStatementForSqlObject($query)->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
    }

    /**
     * Finds a parent (first ancestor) of a node
     *
     * @param mixed      $id Node identifier
     * @param array|null $columns
     * @param bool|null  $includeSearchingNode
     * @return ResultInterface
     * @throws InvalidNodeIdentifierException
     * @throws UnknownDbException
     */
    public function findParent($id, $columns = null, $includeSearchingNode = null)
    {
        if (! is_int($id) && ! is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }
        $query = $this->getFindParentQuery($columns, $includeSearchingNode);

        $parameters = [':id' => $id];

        $result = $this->sql->prepareStatementForSqlObject($query)->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
    }

    /**
     * Find descendants of a node
     *
     * @param mixed             $id Node identifier
     * @param array|null        $columns
     * @param array|string|null $order
     * @param Predicate|null    $where
     * @param null|int          $depthLimit
     * @param bool|null         $includeSearchingNode
     * @return ResultInterface
     * @throws InvalidNodeIdentifierException
     * @throws UnknownDbException
     */
    public function findDescendants(
        $id,
        $columns = null,
        $order = null,
        Predicate $where = null,
        $depthLimit = null,
        $includeSearchingNode = null
    ) {
        if (! is_int($id) && ! is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $parameters = [
            ':id' => $id,
        ];

        $query = $this->getFindDescendantsQuery($columns, $order, $where, $depthLimit, $includeSearchingNode);

        $result = $this->sql->prepareStatementForSqlObject($query)->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
    }

    /**
     * Finds children (direct descendants) of a node
     *
     * @param mixed             $id Node identifier
     * @param array|null        $columns
     * @param array|string|null $order
     * @param Predicate|null    $where
     * @param bool|null         $includeSearchingNode
     * @return ResultInterface
     * @throws InvalidNodeIdentifierException
     */
    public function findChildren(
        $id,
        $columns = null,
        $order = null,
        Predicate $where = null,
        $includeSearchingNode = null
    ) {
        if (! is_int($id) && ! is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $query = $this->getFindChildrenQuery($columns, $order, $where, $includeSearchingNode);

        $parameters = [':id' => $id];

        $result = $this->sql->prepareStatementForSqlObject($query)->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
    }

    /**
     * Finds first child of a node
     *
     * @param mixed      $id Node identifier
     * @param array|null $columns
     * @param bool|null  $includeSearchingNode
     * @return ResultInterface
     * @throws InvalidNodeIdentifierException
     * @throws UnknownDbException
     */
    public function findFirstChild($id, $columns = null, $includeSearchingNode = null)
    {
        if (! is_int($id) && ! is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $parameters = [':id' => $id];

        $query = $this->getFindFirstChildQuery($columns, $includeSearchingNode);

        $result = $this->sql->prepareStatementForSqlObject($query)->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
    }

    /**
     * Finds last child of a node
     *
     * @param mixed      $id Node identifier
     * @param array|null $columns
     * @param bool|null  $includeSearchingNode
     * @return ResultInterface
     * @throws InvalidNodeIdentifierException
     * @throws UnknownDbException
     */
    public function findLastChild($id, $columns = null, $includeSearchingNode = null)
    {
        if (! is_int($id) && ! is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $parameters = [':id' => $id];

        $query = $this->getFindLastChildQuery($columns, $includeSearchingNode);

        $result = $this->sql->prepareStatementForSqlObject($query)->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
    }

    /**
     * Finds sibling of a node
     *
     * @param mixed             $id Node identifier
     * @param array|null        $columns
     * @param array|string|null $order
     * @param Predicate|null    $where
     * @param bool|null         $includeSearchingNode
     * @return ResultInterface
     * @throws InvalidNodeIdentifierException
     * @throws UnknownDbException
     */
    public function findSiblings(
        $id,
        $columns = null,
        $order = null,
        Predicate $where = null,
        $includeSearchingNode = null
    ) {
        if (! is_int($id) && ! is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $parameters = [':id' => $id];

        $query = $this->getFindSiblingsQuery($columns, $order, $where, $includeSearchingNode);

        $result = $this->sql->prepareStatementForSqlObject($query)->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
    }

    /**
     * Finds next sibling of a node
     *
     * @param mixed      $id Node identifier
     * @param array|null $columns
     * @param bool|null  $includeSearchingNode
     * @return ResultInterface
     * @throws InvalidNodeIdentifierException
     * @throws UnknownDbException
     */
    public function findNextSibling($id, $columns = null, $includeSearchingNode = null)
    {
        if (! is_int($id) && ! is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $parameters = [':id' => $id];

        $query = $this->getFindNextSiblingQuery($columns, $includeSearchingNode);

        $result = $this->sql->prepareStatementForSqlObject($query)->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
    }

    /**
     * Finds previous sibling of a node
     *
     * @param mixed      $id Node identifier
     * @param array|null $columns
     * @param bool|null  $includeSearchingNode
     * @return ResultInterface
     * @throws InvalidNodeIdentifierException
     * @throws UnknownDbException
     */
    public function findPreviousSibling($id, $columns = null, $includeSearchingNode = null)
    {
        if (! is_int($id) && ! is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $parameters = [':id' => $id];

        $query = $this->getFindPreviousSiblingQuery($columns, $includeSearchingNode);

        $result = $this->sql->prepareStatementForSqlObject($query)->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
    }
}
