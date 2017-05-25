<?php

namespace metalinspired\NestedSet\Find;

use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;

class FindSiblings extends AbstractFind
{
    public function buildStatement()
    {
        $subSelectSelect = new Select(['parent' => $this->config->getTable()]);

        $subSelectSelect
            ->columns([
                $this->config->getLeftColumn(),
                $this->config->getRightColumn()
            ])
            ->join(
                ['node' => $this->config->getTable()],
                new Expression(
                    '?.? = :id',
                    [
                        ['node' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getIdColumn() => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                []
            )
            ->order(
                new Expression(
                    '?.? DESC',
                    [
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER]
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
                        [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                new Expression(
                    '?.?',
                    [
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER]
                    ]
                )
            )
            ->lessThan(
                new Expression(
                    '?.?',
                    [
                        ['node' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getRightColumn() => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                new Expression(
                    '?.?',
                    [
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getRightColumn() => Expression::TYPE_IDENTIFIER]
                    ]
                )
            );

        $subSelect = new Select(['head_parent' => $subSelectSelect]);

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
                    '?.? Between ?.? AND ?.?',
                    [
                        ['child' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER],
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER],
                        ['parent' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getRightColumn() => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                [$this->config->getIdColumn()]
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
                            [$this->config->getIdColumn() => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    new Expression(':childId')
                );
        }

        $select = new Select(['q' => $subSelect]);

        $select
            ->columns([])
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
            ->order(
                new Expression(
                    '?.? ASC',
                    [
                        ['t' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER]
                    ]
                )
            );

        return $this->sql->prepareStatementForSqlObject($select);
    }

    public function find($id)
    {
        if (!$this->isCached()) {
            $this->statement = $this->buildStatement();
        }

        $parameters = [':id' => $id];

        if (!$this->includeSearchingNode) {
            $parameters[':childId'] = $id;
        }

        return $this->statement->execute($parameters);
    }
}
