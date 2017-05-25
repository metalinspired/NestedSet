<?php

namespace metalinspired\NestedSet\Find;

use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;

class FindAncestors extends AbstractFind implements DepthLimitInterface
{
    use DepthLimitTrait;

    /**
     * {@inheritdoc}
     */
    protected function buildStatement()
    {
        $subSelect = new Select($this->config->getTable());

        $subSelect
            ->columns([
                $this->config->getLeftColumn(),
                $this->config->getRightColumn()
            ], false)
            ->where
            ->equalTo($this->config->getIdColumn(), new Expression(':id'));

        $select = new Select(['q' => $subSelect]);

        $joinOn = '?.? <' . ($this->includeSearchingNode ? '=' : '') . ' ?.?' .
            ' AND ?.? >' . ($this->includeSearchingNode ? '=' : '') . ' ?.?' .
            ' AND ?.? > ?';

        $joinOn = new Expression(
            $joinOn,
            [
                ['t' => Expression::TYPE_IDENTIFIER],
                [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER],
                ['q' => Expression::TYPE_IDENTIFIER],
                [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER],
                ['t' => Expression::TYPE_IDENTIFIER],
                [$this->config->getRightColumn() => Expression::TYPE_IDENTIFIER],
                ['q' => Expression::TYPE_IDENTIFIER],
                [$this->config->getRightColumn() => Expression::TYPE_IDENTIFIER],
                ['t' => Expression::TYPE_IDENTIFIER],
                [$this->config->getIdColumn() => Expression::TYPE_IDENTIFIER],
                $this->config->getRootNodeId()
            ]
        );

        $select
            ->columns([])
            ->join(
                ['t' => $this->config->getTable()],
                $joinOn,
                $this->columns
            )
            ->order(
                new Expression(
                    '?.? DESC',
                    [
                        ['t' => Expression::TYPE_IDENTIFIER],
                        [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER]
                    ]
                )
            );

        if ($this->depthLimit) {
            $depthLimit = $this->depthLimit;
            if ($this->includeSearchingNode) {
                $depthLimit++;
            }
            $select->limit($depthLimit);
        }

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

        $parameters = [':id' => $id];

        return $this->statement->execute($parameters);
    }
}
