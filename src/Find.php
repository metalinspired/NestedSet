<?php

namespace metalinspired\NestedSet;

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
     * @var bool
     */
    protected $includeSearchingNode = false;

    /**
     * @var array
     */
    protected $columns = [Select::SQL_STAR];

    /**
     * @var Join
     */
    protected $joins = [];

    /**
     * @var int
     */
    protected $depthLimit = null;

    public function __construct(Config $config)
    {
        parent::__construct($config);
        $this->joins = new Join();
    }

    /**
     * @return bool
     */
    public function getIncludeSearchingNode()
    {
        return $this->includeSearchingNode;
    }

    /**
     * @param bool $includeSearchingNode
     * @return $this Provides a fluent interface
     */
    public function setIncludeSearchingNode($includeSearchingNode)
    {
        $this->statements = [];
        $this->includeSearchingNode = $includeSearchingNode;
        return $this;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * @param array $columns
     */
    public function setColumns(array $columns)
    {
        $this->statements = [];
        $this->columns = $columns;
        return $this;
    }

    public function join($name, $on, $columns = [Select::SQL_STAR], $type = Join::JOIN_INNER)
    {
        $this->statements = [];
        $this->joins->join($name, $on, $columns, $type);
        return $this;
    }

    public function resetJoins()
    {
        $this->statements = [];
        $this->joins->reset();
        return $this;
    }

    public function getDepthLimit()
    {
        return $this->depthLimit;
    }

    public function setDepthLimit($depthLimit)
    {
        $this->statements = [];
        $this->depthLimit = $depthLimit;
        return $this;
    }

    protected function getFindAncestorsQuery($depthLimit = null)
    {
        $subSelect = new Select($this->getTable());

        $subSelect
            ->columns([
                $this->leftColumn,
                $this->rightColumn
            ], false)
            ->where
            ->equalTo($this->idColumn, new Expression(':id'));

        $select = new Select(['q' => $subSelect]);

        $joinOn = '?.? <' . ($this->includeSearchingNode ? '=' : '') . ' ?.?' .
            ' AND ?.? >' . ($this->includeSearchingNode ? '=' : '') . ' ?.?' .
            ' AND ?.? > ?';

        $joinOn = new Expression(
            $joinOn,
            [
                ['t' => Expression::TYPE_IDENTIFIER],
                [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                ['q' => Expression::TYPE_IDENTIFIER],
                [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                ['t' => Expression::TYPE_IDENTIFIER],
                [$this->rightColumn => Expression::TYPE_IDENTIFIER],
                ['q' => Expression::TYPE_IDENTIFIER],
                [$this->rightColumn => Expression::TYPE_IDENTIFIER],
                ['t' => Expression::TYPE_IDENTIFIER],
                [$this->idColumn => Expression::TYPE_IDENTIFIER],
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
                    '?.? DESC',
                    [
                        ['t' => Expression::TYPE_IDENTIFIER],
                        [$this->leftColumn => Expression::TYPE_IDENTIFIER]
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

    protected function getFindDescendantsQuery($depthLimit = null)
    {
        $subSelect = new Select(['head_parent' => $this->table]);

        $subSelect
            ->columns([])
            ->join(
                ['parent' => $this->table],
                new Expression(
                    '?.? >= ?.? AND ?.? < ?.?',
                    [
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                        ['head_parent' => Expression::TYPE_IDENTIFIER],
                        [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->rightColumn => Expression::TYPE_IDENTIFIER],
                        ['head_parent' => Expression::TYPE_IDENTIFIER],
                        [$this->rightColumn => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                []
            )
            ->join(
                ['child' => $this->table],
                new Expression(
                    '?.? BETWEEN ?.? AND ?.?',
                    [
                        ['child' => Expression::TYPE_IDENTIFIER],
                        [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->rightColumn => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                [
                    'id' => new Expression(
                        '?.?',
                        [
                            ['child' => Expression::TYPE_IDENTIFIER],
                            [$this->idColumn => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    'depth' => new Expression(
                        '(CASE WHEN ?.? = :childDepthId THEN 0 ELSE COUNT(*) END)',
                        [
                            ['child' => Expression::TYPE_IDENTIFIER],
                            [$this->idColumn => Expression::TYPE_IDENTIFIER]
                        ]
                    )
                ]
            )
            ->group(
                new Expression(
                    '?.?',
                    [
                        ['child' => Expression::TYPE_IDENTIFIER],
                        [$this->idColumn => Expression::TYPE_IDENTIFIER]
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
                    '?.?',
                    [
                        ['head_parent' => Expression::TYPE_IDENTIFIER],
                        [$this->idColumn => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                new Expression(':id')
            )
            ->greaterThan(
                new Expression(
                    '?.?',
                    [
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->idColumn => Expression::TYPE_IDENTIFIER]
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
                        '?.?',
                        [
                            ['child' => Expression::TYPE_IDENTIFIER],
                            [$this->idColumn => Expression::TYPE_IDENTIFIER]
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
                    '?.? = ?.?',
                    [
                        ['t' => Expression::TYPE_IDENTIFIER],
                        [$this->idColumn => Expression::TYPE_IDENTIFIER],
                        ['q' => Expression::TYPE_IDENTIFIER],
                        [$this->idColumn => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                $this->columns
            )
            ->order(new Expression(
                    '?.? ASC',
                    [
                        ['t' => Expression::TYPE_IDENTIFIER],
                        [$this->leftColumn => Expression::TYPE_IDENTIFIER]
                    ]
                )
            );

        foreach ($this->joins as $join) {
            $select->join($join['name'], $join['on'], $join['columns'], $join['type']);
        }

        return $select;
    }

    protected function getFindChildQuery($last = false)
    {
        $select = new Select(['t' => $this->table]);

        $select
            ->columns($this->columns)
            ->join(
                ['q' => $this->table],
                new Expression(
                    '?.? = :id',
                    [
                        ['q' => Expression::TYPE_IDENTIFIER],
                        [$this->idColumn => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                []
            )
            ->order(
                new Expression(
                    '?.?',
                    [
                        ['q' => Expression::TYPE_IDENTIFIER],
                        [$this->leftColumn => Expression::TYPE_IDENTIFIER]
                    ]
                )
            )
            ->where
            ->greaterThan(
                new Expression(
                    '?.?',
                    [
                        ['t' => Expression::TYPE_IDENTIFIER],
                        [$this->idColumn => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                $this->rootNodeId
            );

        $predicate = new Predicate();

        $column = $last ? $this->rightColumn : $this->leftColumn;

        $predicate->equalTo(
            new Expression(
                '?.?',
                [
                    ['t' => Expression::TYPE_IDENTIFIER],
                    [$column => Expression::TYPE_IDENTIFIER]
                ]
            ),
            new Expression(
                '?.?' . ($last ? '-' : '+') . '1',
                [
                    ['q' => Expression::TYPE_IDENTIFIER],
                    [$column => Expression::TYPE_IDENTIFIER]
                ]
            )
        );

        if ($this->includeSearchingNode) {
            $predicate
                ->or
                ->equalTo(
                    new Expression(
                        '?.?',
                        [
                            ['t' => Expression::TYPE_IDENTIFIER],
                            [$this->idColumn => Expression::TYPE_IDENTIFIER]
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
                    '?.? = :id',
                    [
                        ['node' => Expression::TYPE_IDENTIFIER],
                        [$this->idColumn => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                []
            )
            ->order(
                new Expression(
                    '?.? DESC',
                    [
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->leftColumn => Expression::TYPE_IDENTIFIER]
                    ]
                )
            )
            ->limit(1)
            ->where
            ->greaterThan(
                new Expression(
                    '?.?',
                    [
                        ['node' => Expression::TYPE_IDENTIFIER],
                        [$this->leftColumn => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                new Expression(
                    '?.?',
                    [
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->leftColumn => Expression::TYPE_IDENTIFIER]
                    ]
                )
            )
            ->lessThan(
                new Expression(
                    '?.?',
                    [
                        ['node' => Expression::TYPE_IDENTIFIER],
                        [$this->rightColumn => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                new Expression(
                    '?.?',
                    [
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->rightColumn => Expression::TYPE_IDENTIFIER]
                    ]
                )
            );

        $subSelect = new Select(['head_parent' => $subSelectSelect]);

        $subSelect
            ->columns([])
            ->join(
                ['parent' => $this->table],
                new Expression(
                    '?.? >= ?.? AND ?.? < ?.?',
                    [
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                        ['head_parent' => Expression::TYPE_IDENTIFIER],
                        [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->rightColumn => Expression::TYPE_IDENTIFIER],
                        ['head_parent' => Expression::TYPE_IDENTIFIER],
                        [$this->rightColumn => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                []
            )
            ->join(
                ['child' => $this->table],
                new Expression(
                    '?.? Between ?.? AND ?.?',
                    [
                        ['child' => Expression::TYPE_IDENTIFIER],
                        [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->rightColumn => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                [$this->idColumn]
            )
            ->group(
                new Expression(
                    '?.?',
                    [
                        ['child' => Expression::TYPE_IDENTIFIER],
                        [$this->idColumn => Expression::TYPE_IDENTIFIER]
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
                        '?.?',
                        [
                            ['child' => Expression::TYPE_IDENTIFIER],
                            [$this->idColumn => Expression::TYPE_IDENTIFIER]
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
                    '?.? = ?.?',
                    [
                        ['t' => Expression::TYPE_IDENTIFIER],
                        [$this->idColumn => Expression::TYPE_IDENTIFIER],
                        ['q' => Expression::TYPE_IDENTIFIER],
                        [$this->idColumn => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                $this->columns
            )
            ->order(
                new Expression(
                    '?.? ASC',
                    [
                        ['t' => Expression::TYPE_IDENTIFIER],
                        [$this->leftColumn => Expression::TYPE_IDENTIFIER]
                    ]
                )
            );

        return $select;
    }

    protected function getFindSiblingQuery($previous = false)
    {
        $select = new Select(['t' => $this->table]);

        $select
            ->columns($this->columns)
            ->join(
                ['q' => $this->table],
                new Expression(
                    '?.? = :id',
                    [
                        ['q' => Expression::TYPE_IDENTIFIER],
                        [$this->idColumn => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                []
            )
            ->order(
                new Expression(
                    '?.? ' . ($previous ? 'DESC' : 'ASC'),
                    [
                        ['t' => Expression::TYPE_IDENTIFIER],
                        [$this->leftColumn => Expression::TYPE_IDENTIFIER]
                    ]
                )
            )
            ->where
            ->greaterThan(
                new Expression(
                    '?.?',
                    [
                        ['t' => Expression::TYPE_IDENTIFIER],
                        [$this->idColumn => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                $this->rootNodeId
            );

        $predicate = new Predicate();

        $column1 = $previous ? $this->rightColumn : $this->leftColumn;
        $column2 = $previous ? $this->leftColumn : $this->rightColumn;

        $predicate->equalTo(
            new Expression(
                '?.?',
                [
                    ['t' => Expression::TYPE_IDENTIFIER],
                    [$column1 => Expression::TYPE_IDENTIFIER]
                ]
            ),
            new Expression(
                '?.? ' . ($previous ? '-' : '+') . ' 1',
                [
                    ['q' => Expression::TYPE_IDENTIFIER],
                    [$column2 => Expression::TYPE_IDENTIFIER]
                ]
            )
        );

        if ($this->includeSearchingNode) {
            $predicate
                ->or
                ->equalTo(
                    new Expression(
                        '?.?',
                        [
                            ['t' => Expression::TYPE_IDENTIFIER],
                            [$this->idColumn => Expression::TYPE_IDENTIFIER]
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

    public function findAncestors($id)
    {
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

    public function findParent($id)
    {
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

    public function findDescendants($id)
    {
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

    public function findChildren($id)
    {
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

    public function findFirstChild($id)
    {
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

    public function findLastChild($id)
    {
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

    public function findSiblings($id)
    {
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

    public function findNextSibling($id)
    {
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

    public function findPreviousSibling($id)
    {
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
