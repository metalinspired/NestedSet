<?php

namespace metalinspired\NestedSet\Manipulate;

use metalinspired\NestedSet\Exception;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;

class Move extends AbstractManipulate
{
    /**
     * Constants for move method
     */
    const MOVE_AFTER = 'after',
        MOVE_BEFORE = 'before',
        MOVE_MAKE_CHILD = 'make_child',
        MOVE_DEFAULT = self::MOVE_AFTER;

    public function move($source, $destination, $position = self::MOVE_DEFAULT)
    {
        if (!is_int($source)) {
            throw new Exception\InvalidNodeIdentifierException($source, 'Source node');
        }

        if (!is_int($destination)) {
            throw new Exception\InvalidNodeIdentifierException($destination, 'Destination node');
        }

        if (!is_string($position)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Method expects integer as $where parameter. Instance of %s given',
                is_object($position) ? get_class($position) : gettype($position)
            ));
        }

        if ($position !== self::MOVE_AFTER && $position !== self::MOVE_BEFORE && $position !== self::MOVE_MAKE_CHILD) {
            throw new Exception\InvalidArgumentException(sprintf(
                '$where parameter value can be either \'after\', \'before\' or \'make_child\'. \'%s\' given',
                $position
            ));
        }

        static $lftRgtStatement = null;

        if (null === $lftRgtStatement) {
            $select = new Select($this->config->getTable());

            $select
                ->columns([
                    'lft' => $this->config->getLeftColumn(),
                    'rgt' => $this->config->getRightColumn()
                ])
                ->where
                ->equalTo(
                    $this->config->getIdColumn(),
                    new Expression(':id')
                );

            $lftRgtStatement = $this->sql->prepareStatementForSqlObject($select);
        }

        /*
         * Get left and right values of moving node
         */
        $result = $lftRgtStatement->execute([':id' => $source]);

        if (!$result instanceof ResultInterface || !$result->isQueryResult()) {
            throw new Exception\UnknownDbException();
        }

        if ($result->getAffectedRows() !== 1) {
            throw new Exception\RuntimeException(sprintf(
                'Source node with identifier %s was not found or not unique',
                $source
            ));
        }

        $sourceLeft = (int)$result->current()['lft'];
        $sourceRight = (int)$result->current()['rgt'];

        /*
         * Determine exact destination for moving node
         */
        $result = $lftRgtStatement->execute([':id' => $destination]);

        if (!$result instanceof ResultInterface || !$result->isQueryResult()) {
            throw new Exception\UnknownDbException();
        }

        if ($result->getAffectedRows() !== 1) {
            throw new Exception\RuntimeException(sprintf(
                'Destination node with identifier %s was not found or not unique',
                $source
            ));
        }

        switch ($position) {
            case self::MOVE_AFTER:
                $destination = (int)$result->current()['rgt'] + 1;
                break;
            case self::MOVE_BEFORE:
                $destination = (int)$result->current()['lft'];
                break;
            case self::MOVE_MAKE_CHILD:
                $destination = (int)$result->current()['rgt'];
        }

        return $this->moveRange($sourceLeft, $sourceRight, $destination);
    }

    public function after($source, $destination)
    {
        return $this->move($source, $destination, self::MOVE_AFTER);
    }

    public function before($source, $destination)
    {
        return $this->move($source, $destination, self::MOVE_BEFORE);
    }

    public function makeChild($source, $destination)
    {
        return $this->move($source, $destination, self::MOVE_MAKE_CHILD);
    }

    public function __invoke($source, $destination, $position = self::MOVE_DEFAULT)
    {
        return $this->move($source, $destination, $position);
    }
}
