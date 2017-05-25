<?php

namespace metalinspired\NestedSet\Manipulate;

use metalinspired\NestedSet\Exception\RuntimeException;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Insert;
use Zend\Db\Sql\Select;

class CreateRoot extends AbstractManipulate
{
    /**
     * Creates a root node
     *
     * @return string Root node identifier
     * @throws RuntimeException
     */
    public function create()
    {
        $select = new Select($this->config->getTable());

        $select->columns([
            'count' => new Expression(
                'COUNT(?)',
                [
                    [$this->config->getIdColumn() => Expression::TYPE_IDENTIFIER]
                ]
            )
        ]);

        $result = $this->sql->prepareStatementForSqlObject($select)->execute();

        if ($result->current()['count'] != 0) {
            throw new RuntimeException(
                sprintf(
                    'Can\'t create root node. Table %s is not empty',
                    $this->config->getTable()
                )
            );
        }

        $insert = new Insert($this->config->getTable());

        $insert->values([
            $this->config->getLeftColumn() => 1,
            $this->config->getRightColumn() => 2
        ]);

        $result = $this->sql->prepareStatementForSqlObject($insert)->execute();

        return $result->getGeneratedValue();
    }

    public function __invoke()
    {
        return $this->create();
    }
}
