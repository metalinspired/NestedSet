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

use metalinspired\NestedSet\Exception\InvalidNodeIdentifierException;
use metalinspired\NestedSet\Exception\UnknownDbException;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Sql\Select;

class HybridFind extends Find
{
    use HybridNestedSetTrait;

    /**
     * {@inheritdoc}
     */
    public function getFindParentQuery($columns = null, $includeSearchingNode = null)
    {
        $includeSearchingNode = is_null($includeSearchingNode) ? $this->includeSearchingNode : $includeSearchingNode;

        $select = new Select(['t' => $this->table]);

        $select
            ->columns($columns ? $columns : $this->columns)
            ->order("t.{$this->leftColumn} DESC")
            ->join(
                ['q' => $this->table],
                "q.{$this->parentColumn} = t.{$this->idColumn}" .
                ($includeSearchingNode ? " OR q.{$this->idColumn} = t.{$this->idColumn}" : ''),
                []
            )
            ->where
            ->greaterThan(
                "t.{$this->idColumn}",
                $this->getRootNodeId()
            )
            ->equalTo(
                "q.{$this->idColumn}",
                new Expression(':id')
            );

        return $select;
    }

    /**
     * {@inheritdoc}
     */
    public function getFindDescendantsQuery(
        $columns = null,
        $order = null,
        Predicate $where = null,
        $depthLimit = null,
        $includeSearchingNode = null
    ) {
        $includeSearchingNode = is_null($includeSearchingNode) ? $this->includeSearchingNode : $includeSearchingNode;

        $select = new Select(['t' => $this->table]);

        $select
            ->columns($columns ? $columns : $this->columns)
            ->join(
                ['q' => $this->table],
                "t.{$this->leftColumn} > q.{$this->leftColumn} " .
                "AND t.{$this->rightColumn} < q.{$this->rightColumn}" .
                ($includeSearchingNode ? " OR q.{$this->idColumn} = t.{$this->idColumn}" : ''),
                []
            )
            ->group("t.{$this->idColumn}")
            ->order($order ? $order : "t.{$this->leftColumn}")
            ->where
            ->equalTo(
                "q.{$this->idColumn}",
                new Expression(':id')
            );

        if ($where) {
            $select
                ->where
                ->predicate($where);
        }

        $depthLimit = $depthLimit ? $depthLimit : $this->depthLimit;

        if ($depthLimit) {
            $select
                ->where
                ->lessThanOrEqualTo(
                    "t.{$this->depthColumn}",
                    new Expression(
                        '? + ?',
                        [
                            ["q.{$this->depthColumn}" => Expression::TYPE_IDENTIFIER],
                            $depthLimit,
                        ]
                    )
                );
        }

        return $select;
    }

    /**
     * {@inheritdoc}
     */
    public function getFindChildrenQuery(
        $columns = null,
        $order = null,
        Predicate $where = null,
        $includeSearchingNode = null
    ) {
        $includeSearchingNode = is_null($includeSearchingNode) ? $this->includeSearchingNode : $includeSearchingNode;

        $subSelect = new Select(['parent' => $this->table]);

        $subSelect
            ->columns([])
            ->join(
                ['children' => $this->table],
                "children.{$this->parentColumn} = parent.{$this->idColumn}" .
                ($includeSearchingNode ? " OR children.id = parent.{$this->idColumn}" : ''),
                ['id' => $this->idColumn]
            )
            ->where
            ->equalTo(
                "parent.{$this->idColumn}",
                new Expression(':id')
            );


        $select = new Select(['q' => $subSelect]);

        $select
            ->columns([])
            ->join(
                ['t' => $this->table],
                "q.id = t.id",
                $columns ? $columns : $this->columns
            )
            ->order($order ? $order : "t.{$this->leftColumn}");

        if ($where) {
            $select
                ->where
                ->predicate($where);
        }

        return $select;
    }

    /**
     * {@inheritdoc}
     */
    public function getFindSiblingsQuery(
        $columns = null,
        $order = null,
        Predicate $where = null,
        $includeSearchingNode = null
    ) {
        $includeSearchingNode = is_null($includeSearchingNode) ? $this->includeSearchingNode : $includeSearchingNode;

        $select = new Select(['t' => $this->table]);

        $select
            ->columns($columns ? $columns : $this->columns)
            ->join(
                [
                    'q' => (new Select($this->table))
                        ->columns([
                            'id' => $this->idColumn,
                            'parent' => $this->parentColumn,
                        ], false)
                        ->group('id'),
                ],
                "q.parent = t.{$this->parentColumn}" .
                (! $includeSearchingNode ? " AND q.{$this->idColumn} <> t.{$this->idColumn}" : ''),
                []
            )
            ->order($order ? $order : "t.{$this->leftColumn}")
            ->where
            ->equalTo(
                "q.{$this->idColumn}",
                new Expression(':id')
            );

        if ($where) {
            $select
                ->where
                ->predicate($where);
        }

        return $select;
    }

    /**
     * {@inheritdoc}
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
            throw new InvalidNodeIdentifierException($id);
        }

        $query = $this->getFindDescendantsQuery($columns, $order, $where, $depthLimit, $includeSearchingNode);

        $parameters = [':id' => $id];

        $result = $this->sql->prepareStatementForSqlObject($query)->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function findChildren(
        $id,
        $columns = null,
        $order = null,
        Predicate $where = null,
        $includeSearchingNode = null
    ) {
        if (! is_int($id) && ! is_string($id)) {
            throw new InvalidNodeIdentifierException($id);
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
     * {@inheritdoc}
     */
    public function findParent($id, $columns = null, $includeSearchingNode = null)
    {
        if (! is_int($id) && ! is_string($id)) {
            throw new InvalidNodeIdentifierException($id);
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
     * {@inheritdoc}
     */
    public function findSiblings(
        $id,
        $columns = null,
        $order = null,
        Predicate $where = null,
        $includeSearchingNode = null
    ) {
        if (! is_int($id) && ! is_string($id)) {
            throw new InvalidNodeIdentifierException($id);
        }

        $query = $this->getFindSiblingsQuery($columns, $order, $where, $includeSearchingNode);

        $parameters = [':id' => $id];

        $result = $this->sql->prepareStatementForSqlObject($query)->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
    }
}
