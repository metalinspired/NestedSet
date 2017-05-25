<?php

namespace metalinspired\NestedSet\Find;

use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Sql\Select;

class FindNextSibling extends AbstractFind
{
    protected function buildStatement()
    {
        $select = new Select(['t' => $this->config->getTable()]);

        $select
            ->columns($this->columns)
            ->join(
                ['q' => $this->config->getTable()],
                new Expression(
                    '?.? = :id',
                    [
                        ['q' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getIdColumn() => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                []
            )
            ->order(
                new Expression(
                    '?.? ASC',
                    [
                        ['t' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER]
                    ]
                )
            )
            ->where
            ->greaterThan(
                new Expression(
                    '?.?',
                    [
                        ['t' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getIdColumn() => Expression::TYPE_IDENTIFIER]
                    ]
                ),
                $this->config->getRootNodeId()
            );

        $predicate = new Predicate();

        $predicate->equalTo(
            new Expression(
                '?.?',
                [
                    ['t' => Expression::TYPE_IDENTIFIER],
                    [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER]
                ]
            ),
            new Expression(
                '?.? + 1',
                [
                    ['q' => Expression::TYPE_IDENTIFIER],
                    [$this->config->getRightColumn() => Expression::TYPE_IDENTIFIER]
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
                            [$this->config->getIdColumn() => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    new Expression(':includeId')
                );
        }

        $select
            ->where
            ->addPredicate($predicate);

        return $this->sql->prepareStatementForSqlObject($select);
    }

    public function find($id)
    {
        if (!$this->isCached()) {
            $this->statement = $this->buildStatement();
        }

        $parameters = [':id' => $id];

        if ($this->includeSearchingNode) {
            $parameters[':includeId'] = $id;
        }

        return $this->statement->execute($parameters);
    }
}
