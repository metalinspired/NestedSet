<?php

namespace metalinspired\NestedSet\Find;

use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;

class FindDescendants extends AbstractFind implements DepthLimitInterface
{
    use DepthLimitTrait;

    protected function buildStatement()
    {
        $subSelect = new Select(['head_parent' => $this->config->getTable()]);

        $subSelect
            ->columns([])
            ->join(
                ['parent' => $this->config->getTable()],
                new Expression(
                    '?.? >= ?.? AND ?.? < ?.?',
                    [
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER],
                        ['head_parent' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER],
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getRightColumn() => Expression::TYPE_IDENTIFIER],
                        ['head_parent' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getRightColumn() => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                []
            )
            ->join(
                ['child' => $this->config->getTable()],
                new Expression(
                    '?.? BETWEEN ?.? AND ?.?',
                    [
                        ['child' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER],
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER],
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getRightColumn() => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                [
                    'id' => new Expression(
                        '?.?',
                        [
                            ['child' => Expression::TYPE_IDENTIFIER],
                            [$this->config->getIdColumn() => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    'depth' => new Expression(
                        '(CASE WHEN ?.? = :childDepthId THEN 0 ELSE COUNT(*) END)',
                        [
                            ['child' => Expression::TYPE_IDENTIFIER],
                            [$this->config->getIdColumn() => Expression::TYPE_IDENTIFIER]
                        ]
                    )
                ]
            )
            ->group(
                new Expression(
                    '?.?',
                    [
                        ['child' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getIdColumn() => Expression::TYPE_IDENTIFIER]
                    ]
                )
            );

        $subSelect->having
            ->greaterThanOrEqualTo(new Expression('COUNT(*)'), 1);

        if ($this->depthLimit) {
            $subSelect->having
                ->lessThanOrEqualTo(new Expression('COUNT(*)'), $this->depthLimit);
        }

        $subSelect->where
            ->equalTo(
                new Expression(
                    '?.?',
                    [
                        ['head_parent' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getIdColumn() => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                new Expression(':id')
            )
            ->greaterThan(
                new Expression(
                    '?.?',
                    [
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getIdColumn() => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                $this->config->getRootNodeId()
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
                            [$this->config->getIdColumn() => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    new Expression(':searchNodeId'));
        }

        $select = new Select(['q' => $subSelect]);

        $select
            ->columns(['depth'])
            ->join(
                ['t' => $this->config->getTable()],
                new Expression(
                    '?.? = ?.?',
                    [
                        ['t' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getIdColumn() => Expression::TYPE_IDENTIFIER],
                        ['q' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getIdColumn() => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                $this->columns
            )
            ->order(new Expression(
                    '?.? ASC',
                    [
                        ['t' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER]
                    ]
                )
            );

        foreach ($this->joins as $join) {
            $select->join($join['name'], $join['on'], $join['columns'], $join['type']);
        }

        return $this->sql->prepareStatementForSqlObject($select);
    }

    public function find($id)
    {
        if (!$this->isCached()) {
            $this->statement = $this->buildStatement();
        }

        $parameters = [
            ':id' => $id,
            ':childDepthId' => $id
        ];

        if ($this->includeSearchingNode) {
            $parameters[':searchNodeId'] = $id;
        }

        return $this->statement->execute($parameters);
    }
}
