<?php

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
    public function getFindParentQuery($columns = null)
    {
        $select = new Select(['t' => $this->table]);

        $select
            ->columns($columns ? $columns : $this->columns)
            ->order("t.{$this->leftColumn} DESC")
            ->join(
                ['q' => $this->table],
                "q.{$this->parentColumn} = t.{$this->idColumn}" .
                ($this->includeSearchingNode ? " OR q.{$this->idColumn} = t.{$this->idColumn}" : ''),
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
        $depthLimit = null
    ) {
        $select = new Select(['t' => $this->table]);

        $select
            ->columns($columns ? $columns : $this->columns)
            ->join(
                ['q' => $this->table],
                "t.{$this->leftColumn} > q.{$this->leftColumn} " .
                "AND t.{$this->rightColumn} < q.{$this->rightColumn}" .
                ($this->includeSearchingNode ? " OR q.{$this->idColumn} = t.{$this->idColumn}" : ''),
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
        Predicate $where = null
    ) {
        $subSelect = new Select(['parent'=> $this->table]);

        $subSelect
            ->columns([])
            ->join(
                ['children' => $this->table],
                "children.{$this->parentColumn} = parent.{$this->idColumn}" .
                ($this->includeSearchingNode ? " OR children.id = parent.{$this->idColumn}" : ''),
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
    public function getFindSiblingsQuery($columns = null, $order = null, Predicate $where = null)
    {
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
                (! $this->includeSearchingNode ? " AND q.{$this->idColumn} <> t.{$this->idColumn}" : ''),
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
        $depthLimit = null
    ) {
        if (! is_int($id) && ! is_string($id)) {
            throw new InvalidNodeIdentifierException($id);
        }

        $query = $this->getFindDescendantsQuery($columns, $order, $where, $depthLimit);

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
        Predicate $where = null
    ) {
        if (! is_int($id) && ! is_string($id)) {
            throw new InvalidNodeIdentifierException($id);
        }

        $query = $this->getFindChildrenQuery($columns, $order, $where);

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
    public function findParent($id, $columns = null)
    {
        if (! is_int($id) && ! is_string($id)) {
            throw new InvalidNodeIdentifierException($id);
        }

        $query = $this->getFindParentQuery($columns);

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
        Predicate $where = null
    ) {
        if (! is_int($id) && ! is_string($id)) {
            throw new InvalidNodeIdentifierException($id);
        }

        $query = $this->getFindSiblingsQuery($columns, $order, $where);

        $parameters = [':id' => $id];

        $result = $this->sql->prepareStatementForSqlObject($query)->execute($parameters);

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new UnknownDbException();
        }

        return $result;
    }
}
