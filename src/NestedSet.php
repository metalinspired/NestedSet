<?php

namespace metalinspired\NestedSet;

use metalinspired\NestedSet\Exception;

class NestedSet
    extends AbstractNestedSet
{
    /**
     * Constants for move method
     */
    const MOVE_AFTER = 'after',
        MOVE_BEFORE = 'before',
        MOVE_CHILD = 'child',
        MOVE_DEFAULT = self::MOVE_AFTER;

    /**
     * @param string $query
     * @return string Identifier of inserted row
     * @throws Exception\RuntimeException
     */
    protected function executeInsert($query)
    {
        $statement = $this->prepareStatement($query);
        $execution = $statement->execute();

        if (false === $execution) {
            throw new Exception\RuntimeException('Could not execute insert query');
        }

        return $this->pdo->lastInsertId();
    }

    /**
     * @param string $query
     * @return int Number of affected rows
     * @throws Exception\RuntimeException
     */
    protected function executeUpdate($query)
    {
        $statement = $this->prepareStatement($query);
        $execution = $statement->execute();

        if (false === $execution) {
            throw new Exception\RuntimeException('Could not execute update query');
        }

        return $statement->rowCount();
    }

    /**
     * @param $query
     * @return int
     * @throws Exception\RuntimeException
     */
    protected function executeDelete($query)
    {
        $statement = $this->prepareStatement($query);
        $execution = $statement->execute();

        if (false === $execution) {
            throw new Exception\RuntimeException('Could not execute delete query');
        }

        return $statement->rowCount();
    }

    /**
     * Creates a root node in a table
     *
     * @param string|null $table
     * @return string
     */
    public function createRootNode($table = null)
    {
        $table = $this->getTable($table);

        if (null === $table) {
            throw new Exception\NoTableSetException();
        }

        $table = $this->quoteName($table);
        $leftColumn = $this->quoteName($this->getLeftColumn());
        $rightColumn = $this->quoteName($this->getRightColumn());

        $query = "INSERT INTO {$table} ({$leftColumn}, {$rightColumn}) VALUES (1, 2)";

        return $this->executeInsert($query);
    }

    /**
     * Inserts new node with passed data
     *
     * @param array $data Data for new node
     * @param int $parent Identifier of parent node
     * @param string|null $table Table name
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     * @return int Identifier for newly created node
     */
    public function insert(array $data, $parent, $table = null)
    {
        if (!is_int($parent)) {
            throw new Exception\InvalidNodeIdentifierException($parent, "Parent node");
        }

        $table = $this->getTable($table);

        if (null === $table) {
            throw new Exception\NoTableSetException();
        }

        $table = $this->quoteName($table);
        $rightColumn = $this->quoteName($this->getRightColumn());
        $leftColumn = $this->quoteName($this->getLeftColumn());
        $idColumn = $this->quoteName($this->getIdColumn());

        /*
         * Get parents right column value as left column value for new node
         */
        $query = "SELECT {$rightColumn} AS rgt FROM {$table} WHERE {$idColumn} = {$parent}";

        $result = $this->executeSelect($query);

        if (0 === count($result)) {
            throw new Exception\RuntimeException(sprintf(
                "Parent with identifier %s was not found",
                $parent
            ));
        }

        $newPosition = (int)$result['rgt'];

        /*
         * Create a gap to insert new record
         */
        $query = "UPDATE {$table} SET " .
            "{$rightColumn} = {$rightColumn} + 2, " .
            "{$leftColumn} = (CASE WHEN {$leftColumn} > {$newPosition} THEN {$leftColumn} + 2 ELSE {$leftColumn} END) " .
            "WHERE {$rightColumn} >= {$newPosition}";

        $this->executeUpdate($query);

        /*
         * Insert new data
         */
        $data[$this->getLeftColumn()] = $newPosition;
        $data[$this->getRightColumn()] = $newPosition + 1;

        $query = "INSERT INTO {$table} " .
            "(" . implode(',', array_map([$this, 'quoteName'], array_keys($data))) . ") " .
            "VALUES " .
            "(" . implode(',', array_map([$this, 'quoteValue'], $data)) . ")";

        return $this->executeInsert($query);
    }

    /**
     * Moves a node
     * Note: If node has children they will be moved with node
     *
     * @param int $source Identifier of node to be moved
     * @param int $destination Identifier of an destination node
     * @param string $moveTo Move node to before/after destination or make it a child of destination node
     * @param string|null $table Table name
     * @return int Number of rows affected (Nodes moved)
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     */
    public function move($source, $destination, $moveTo = self::MOVE_DEFAULT, $table = null)
    {
        if (!is_int($source)) {
            throw new Exception\InvalidNodeIdentifierException($source, "Source node");
        }

        if (!is_int($destination)) {
            throw new Exception\InvalidNodeIdentifierException($destination, "Destination node");
        }

        if (!is_string($moveTo)) {
            throw new Exception\InvalidArgumentException(sprintf(
                "Method expects integer as \$where parameter. Instance of %s given",
                is_object($moveTo) ? get_class($moveTo) : gettype($moveTo)
            ));
        }

        if ($moveTo !== self::MOVE_AFTER && $moveTo !== self::MOVE_BEFORE && $moveTo !== self::MOVE_CHILD) {
            throw new Exception\InvalidArgumentException(sprintf(
                "\$where parameter value can be either 'after', 'before' or 'child'. '%s' given",
                $moveTo
            ));
        }

        $table = $this->getTable($table);

        if (null === $table) {
            throw new Exception\NoTableSetException();
        }

        $table = $this->quoteName($table);
        $leftColumn = $this->quoteName($this->getLeftColumn());
        $rightColumn = $this->quoteName($this->getRightColumn());
        $idColumn = $this->quoteName($this->getIdColumn());

        /*
         * Get left and right values of moving node
         */
        $query = "SELECT {$leftColumn} AS lft, {$rightColumn} as rgt FROM {$table} WHERE {$idColumn} = {$source}";

        $result = $this->executeSelect($query);

        if (0 === count($result)) {
            throw new Exception\RuntimeException(sprintf(
                "Source node with identifier: %s was not found",
                $source
            ));
        }

        $sourceLeft = (int)$result['lft'];
        $sourceRight = (int)$result['rgt'];

        /*
         * Determine exact destination for moving node
         */
        $query = "SELECT {$leftColumn} AS lft, {$rightColumn} AS rgt FROM {$table} WHERE {$idColumn} = $destination";

        $result = $this->executeSelect($query);

        if (0 === count($result)) {
            throw new Exception\RuntimeException(sprintf(
                "Destination node with identifier: %s was not found",
                $destination
            ));
        }

        switch ($moveTo) {
            case self::MOVE_AFTER:
                $destination = (int)$result['rgt'] + 1;
                break;
            case self::MOVE_BEFORE:
                $destination = (int)$result['lft'];
                break;
            case self::MOVE_CHILD:
                $destination = (int)$result['rgt'];
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
        $query = "UPDATE {$table} SET " .
            "{$leftColumn} = (CASE WHEN {$leftColumn} >= {$destination} THEN {$leftColumn} + {$size} ELSE {$leftColumn} END), " .
            "{$rightColumn} = (CASE WHEN {$rightColumn} >= {$destination} THEN {$rightColumn} + {$size} ELSE {$rightColumn} END) " .
            "WHERE {$rightColumn} >= {$destination}";

        $this->executeUpdate($query);

        /*
         * Move node to its new position
         */
        $query = "UPDATE {$table} SET " .
            "{$leftColumn} = {$leftColumn} + {$distance}, " .
            "{$rightColumn} = {$rightColumn} + {$distance} " .
            "WHERE {$leftColumn} >= {$sourceLeft} AND {$rightColumn} < {$sourceLeft} + {$size}";

        $result = $this->executeUpdate($query);

        /*
         * Remove space gap created after node has been moved
         */
        $query = "UPDATE {$table} SET " .
            "{$leftColumn} = (CASE WHEN {$leftColumn} > {$sourceRight} THEN {$leftColumn} - {$size} ELSE {$leftColumn} END), " .
            "{$rightColumn} = (CASE WHEN {$rightColumn} > {$sourceRight} THEN {$rightColumn} - {$size} ELSE {$rightColumn} END) " .
            "WHERE {$rightColumn} > {$sourceRight}";

        $this->executeUpdate($query);

        return $result;
    }

    /**
     * Utility method for moving node to position after destination node
     * Proxies to move()
     *
     * @param int $source Node identifier
     * @param int $destination Node identifier
     * @param string|null $table Table name
     * @see move()
     */
    public function moveAfter($source, $destination, $table = null)
    {
        $this->move($source, $destination, self::MOVE_AFTER, $table);
    }

    /**
     * Utility method for moving node to position before destination node
     * Proxies to move()
     *
     * @param int $source Node identifier
     * @param int $destination Node identifier
     * @param string|null $table Table name
     * @see move()
     */
    public function moveBefore($source, $destination, $table = null)
    {
        $this->move($source, $destination, self::MOVE_BEFORE, $table);
    }

    /**
     * Utility method for moving node to become a child of destination node
     * Proxies to move()
     *
     * @param int $source Node identifier
     * @param int $destination Node identifier
     * @param string|null $table Table name
     * @see move()
     */
    public function moveChild($source, $destination, $table = null)
    {
        $this->move($source, $destination, self::MOVE_CHILD, $table);
    }

    /**
     * Deletes a node
     * Note: If node has children they will be deleted too
     *
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @return int Number of rows affected (Nodes deleted)
     * @throws Exception\RuntimeException
     * @throws Exception\InvalidArgumentException
     */
    public function delete($id, $table = null)
    {
        if (!is_int($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $table = $this->getTable($table);

        if (null === $table) {
            throw new Exception\NoTableSetException();
        }

        $table = $this->quoteName($table);
        $leftColumn = $this->quoteName($this->getLeftColumn());
        $rightColumn = $this->quoteName($this->getRightColumn());
        $idColumn = $this->quoteName($this->getIdColumn());

        /*
         * Get right and left values of node that is being deleted
         */
        $query = "SELECT {$leftColumn} AS lft, {$rightColumn} AS rgt FROM {$table} WHERE {$idColumn} = {$id}";

        $result = $this->executeSelect($query);

        if (0 === count($result)) {
            throw new Exception\RuntimeException(sprintf(
                "Node with identifier: %s was not found",
                $id
            ));
        }

        $nodeLeft = (int)$result['lft'];
        $nodeRight = (int)$result['rgt'];

        /*
         * Calculate size of node
         */
        $size = $nodeRight - $nodeLeft + 1;

        /*
         * Delete the node including its children
         */
        $query = "DELETE FROM {$table} WHERE {$leftColumn} >= {$nodeLeft} AND {$rightColumn} <= {$nodeRight}";

        $result = $this->executeDelete($query);

        /*
         * Close the gap left after deleting
         */
        $query = "UPDATE {$table} SET " .
            "{$leftColumn} = (CASE WHEN {$leftColumn} > {$nodeRight} THEN {$leftColumn} - {$size} ELSE {$leftColumn} END), " .
            "{$rightColumn} = (CASE WHEN {$rightColumn} > {$nodeRight} THEN {$rightColumn} - {$size} ELSE {$rightColumn} END) " .
            "WHERE {$rightColumn} > {$nodeRight}";

        $this->executeUpdate($query);

        return $result;
    }
}