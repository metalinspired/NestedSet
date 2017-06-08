<?php

namespace metalinspired\NestedSet;

use Zend\Db\Adapter\Driver\StatementInterface;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Update;

trait CreateInsertGapTrait
{
    /**
     * @return StatementInterface
     */
    protected function getInsertMethodCreateGapStatement()
    {
        /** @var AbstractNestedSet $this */
        /*
         * Create a gap to insert new record
         */
        if (!array_key_exists('create_gap__insert', $this->statements)) {
            $update = new Update($this->table);

            $update
                ->set([
                    $this->rightColumn => new Expression(
                        '? + 2',
                        [
                            [$this->rightColumn => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    $this->leftColumn => new Expression(
                        '(CASE WHEN ? > :newPosition THEN ? + 2 ELSE ? END)',
                        [
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER],
                            [$this->leftColumn => Expression::TYPE_IDENTIFIER]
                        ]
                    )
                ])
                ->where
                ->greaterThanOrEqualTo(
                    $this->rightColumn,
                    new Expression(':newPositionWhere')
                );

            $this->statements['create_gap__insert'] = $this->sql->prepareStatementForSqlObject($update);
        }

        return $this->statements['create_gap__insert'];
    }
}