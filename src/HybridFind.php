<?php

namespace metalinspired\NestedSet;

use Zend\Db\Adapter\Driver\StatementInterface;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;

class HybridFind extends Find
{
    use HybridNestedSetTrait;

    public function findDescendants($id)
    {
        if (!is_int($id) && !is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (!array_key_exists('hybrid_find_descendants', $this->statements)) {
            $select = new Select(['t' => $this->table]);

            $select
                ->columns($this->columns)
                ->join(
                    ['q' => $this->table],
                    "t.{$this->leftColumn} > q.{$this->leftColumn} " .
                    "AND t.{$this->rightColumn} < q.{$this->rightColumn}" .
                    ($this->includeSearchingNode ? " OR q.{$this->idColumn} = t.{$this->idColumn}" : ''),
                    []
                )
                ->group("t.{$this->idColumn}")
                ->order("t.{$this->leftColumn}")
                ->where
                ->equalTo(
                    "q.{$this->idColumn}",
                    new Expression(':id')
                );

            if ($this->depthLimit) {
                $select
                    ->where
                    ->lessThanOrEqualTo(
                        "t.{$this->depthColumn}",
                        new Expression(
                            '? + ?',
                            [
                                ["q.{$this->depthColumn}" => Expression::TYPE_IDENTIFIER],
                                new Expression(':depth')
                            ]
                        )
                    );
            }

            $this->statements['hybrid_find_descendants'] = $this->sql->prepareStatementForSqlObject($select);
        }

        /** @var StatementInterface $statement */
        $statement = $this->statements['hybrid_find_descendants'];

        $parameters = [':id' => $id];

        if ($this->depthLimit) {
            $parameters[':depth'] = $this->depthLimit;
        }

        return $statement->execute($parameters);
    }

    public function findChildren($id)
    {
        if (!is_int($id) && !is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (!array_key_exists('hybrid_find_children', $this->statements)) {
            $select = new Select($this->table);

            $select
                ->columns($this->columns)
                ->order($this->leftColumn)
                ->where
                ->equalTo(
                    $this->parentColumn,
                    new Expression(':id')
                );

            if ($this->includeSearchingNode) {
                $select
                    ->where
                    ->or
                    ->equalTo(
                        $this->idColumn,
                        new Expression(':sId')
                    );
            }

            $this->statements['hybrid_find_children'] = $this->sql->prepareStatementForSqlObject($select);
        }

        /** @var StatementInterface $statement */
        $statement = $this->statements['hybrid_find_children'];

        $parameters = [':id' => $id];

        if ($this->includeSearchingNode) {
            $parameters[':sId'] = $id;
        }

        return $statement->execute($parameters);
    }

    public function findParent($id)
    {
        if (!is_int($id) && !is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (!array_key_exists('hybrid_find_parent', $this->statements)) {
            $select = new Select(['t' => $this->table]);

            $select
                ->columns($this->columns)
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

            $this->statements['hybrid_find_parent'] = $this->sql->prepareStatementForSqlObject($select);
        }

        /** @var StatementInterface $statement */
        $statement = $this->statements['hybrid_find_parent'];

        $parameters = [':id' => $id];

        return $statement->execute($parameters);
    }

    public function findSiblings($id)
    {
        if (!is_int($id) && !is_string($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        if (!array_key_exists('hybrid_find_sibling', $this->statements)) {
            $select = new Select(['t' => $this->table]);

            $select
                ->columns($this->columns)
                ->join(
                    [
                        'q' => (new Select($this->table))
                            ->columns([
                                'id' => $this->idColumn,
                                'parent' => $this->parentColumn
                            ], false)
                            ->group('id')
                    ],
                    "q.parent = t.{$this->parentColumn}" .
                    (!$this->includeSearchingNode ? " AND q.{$this->idColumn} <> t.{$this->idColumn}" : ''),
                    []
                )
                ->order("t.{$this->leftColumn}")
                ->where
                ->equalTo(
                    "q.{$this->idColumn}",
                    new Expression(':id')
                );

            $this->statements['hybrid_find_sibling'] = $this->sql->prepareStatementForSqlObject($select);
        }

        /** @var StatementInterface $statement */
        $statement = $this->statements['hybrid_find_sibling'];

        $parameters = [':id' => $id];

        return $statement->execute($parameters);
    }
}
