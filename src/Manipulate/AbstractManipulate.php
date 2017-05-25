<?php

namespace metalinspired\NestedSet\Manipulate;

use metalinspired\NestedSet\AbstractNestedSet;
use metalinspired\NestedSet\Exception;
use Zend\Db\Adapter\Driver\StatementInterface;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Update;

abstract class AbstractManipulate extends AbstractNestedSet
{
    /**
     * Moves a range between, and including, provided left and right values
     *
     * @param int $sourceLeft  Source node left value
     * @param int $sourceRight Source node right value
     * @param int $destination Destination (left or right) for source node
     * @return int Number of rows affected (Nodes moved)
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     */
    protected function moveRange($sourceLeft, $sourceRight, $destination)
    {
        static $createGapStatement = null;

        static $moveStatement = null;

        if (null === $createGapStatement) {
            $createGapStatement = new Update($this->config->getTable());

            $createGapStatement
                ->set([
                    $this->config->getLeftColumn() => new Expression(
                        '(CASE WHEN ? >= :destination1 THEN ? + :size1 ELSE ? END)',
                        [
                            [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER],
                            [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER],
                            [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    $this->config->getRightColumn() => new Expression(
                        '(CASE WHEN ? >= :destination2 THEN ? + :size2 ELSE ? END)',
                        [
                            [$this->config->getRightColumn() => Expression::TYPE_IDENTIFIER],
                            [$this->config->getRightColumn() => Expression::TYPE_IDENTIFIER],
                            [$this->config->getRightColumn() => Expression::TYPE_IDENTIFIER]
                        ]
                    )
                ])
                ->where
                ->greaterThanOrEqualTo(
                    new Expression(
                        '?',
                        [
                            [$this->config->getRightColumn() => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    new Expression(':destination3')
                );

            $createGapStatement = $this->sql->prepareStatementForSqlObject($createGapStatement);
        }

        if (null === $moveStatement) {
            $moveStatement = new Update($this->config->getTable());

            $moveStatement
                ->set([
                    $this->config->getLeftColumn() => new Expression(
                        '? + :distance1',
                        [
                            [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    $this->config->getRightColumn() => new Expression(
                        '? + :distance2',
                        [
                            [$this->config->getRightColumn() => Expression::TYPE_IDENTIFIER]
                        ]
                    )
                ])
                ->where
                ->greaterThanOrEqualTo(
                    new Expression(
                        '?',
                        [
                            [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    new Expression(':source1')
                )
                ->lessThan(
                    new Expression(
                        '?',
                        [
                            [$this->config->getRightColumn() => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    new Expression(':source2 + :size')
                );

            $moveStatement = $this->sql->prepareStatementForSqlObject($moveStatement);
        }

        /*
         * Calculate size of moving node
         */
        $size = $sourceRight - $sourceLeft + 1;

        /*
         * Calculate the distance between old and new position
         */
        $distance = $destination - $sourceLeft;

        /*
         * Backward movement must account for new space
         */
        if ($distance < 0) {
            $distance -= $size;
            $sourceLeft += $size;
        }

        /*
         * Create gap
         */
        $createGapStatement->execute([
            ':destination1' => $destination,
            ':size1' => $size,
            ':destination2' => $destination,
            ':size2' => $size,
            ':destination3' => $destination
        ]);

        /*
         * Move node to its new position
         */
        $result = $moveStatement->execute([
            ':distance1' => $distance,
            ':distance2' => $distance,
            ':source1' => $sourceLeft,
            ':source2' => $sourceLeft,
            ':size' => $size
        ]);

        /*
         * Remove gap created after node has been moved
         */
        $this->getCloseGapStatement()->execute([
            ':source1' => $sourceRight,
            ':size1' => $size,
            ':source2' => $sourceRight,
            ':size2' => $size,
            ':source3' => $sourceRight
        ]);

        return $result->getAffectedRows();
    }

    /**
     * @return StatementInterface
     */
    protected function getCloseGapStatement()
    {
        /** @var StatementInterface $closeGapStatement */
        static $closeGapStatement = null;

        if (null === $closeGapStatement) {
            $closeGapStatement = new Update($this->config->getTable());

            $closeGapStatement
                ->set([
                    $this->config->getLeftColumn() => new Expression(
                        '(CASE WHEN ? > :source1 THEN ? - :size1 ELSE ? END)',
                        [
                            [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER],
                            [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER],
                            [$this->config->getLeftColumn() => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    $this->config->getRightColumn() => new Expression(
                        '(CASE WHEN ? > :source2 THEN ? - :size2 ELSE ? END)',
                        [
                            [$this->config->getRightColumn() => Expression::TYPE_IDENTIFIER],
                            [$this->config->getRightColumn() => Expression::TYPE_IDENTIFIER],
                            [$this->config->getRightColumn() => Expression::TYPE_IDENTIFIER]
                        ]
                    )
                ])
                ->where
                ->greaterThan(
                    new Expression(
                        '?',
                        [
                            [$this->config->getRightColumn() => Expression::TYPE_IDENTIFIER]
                        ]
                    ),
                    new Expression(':source3')
                );

            $closeGapStatement = $this->sql->prepareStatementForSqlObject($closeGapStatement);
        }

        return $closeGapStatement;
    }
}
