<?php

namespace metalinspired\NestedSet;

use metalinspired\NestedSet\Exception;

class NestedSetSelect
    extends AbstractNestedSet
{
    /**
     * Identifier of root node
     * This is used to omit root node from results
     *
     * @var int
     */
    protected $rootNodeId = 1;

    /**
     * Sets identifier of root node
     *
     * @param $id
     */
    public function setRootNodeId($id)
    {
        if (!is_int($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $this->rootNodeId = $id;
    }

    /**
     * Returns identifier of root node
     *
     * @return int
     */
    public function getRootNodeId()
    {
        return $this->rootNodeId;
    }

    /**
     * Find immediate children of a node
     *
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @param int $fetchStyle PDO fetch style
     * @param bool $includeParent Set to true to include the node whose children are being searched for in result
     * @return array
     */
    public function findChildren($id, $table = null, $fetchStyle = self::FETCH_DEFAULT, $includeParent = false)
    {
        if (!is_int($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $table = $this->getTable($table);

        if (null === $table) {
            throw new Exception\NoTableSetException();
        }

        $table = $this->quoteName($table);
        $idColumn = $this->quoteName($this->getIdColumn());
        $leftColumn = $this->quoteName($this->getLeftColumn());
        $rightColumn = $this->quoteName($this->getRightColumn());

        $query = "SELECT child.* " .
            "FROM {$table} AS parent " .
            "JOIN (SELECT {$leftColumn} AS parentLft, {$rightColumn} AS parentRgt FROM {$table} WHERE {$idColumn} = {$id}) AS parent2 " .
            "JOIN {$table} AS child ON child.{$leftColumn} BETWEEN parent.{$leftColumn} AND parent.{$rightColumn} " .
            "WHERE parent.{$leftColumn} >= parentLft AND parent.{$rightColumn} < parentRgt AND parent.{$idColumn} > {$this->rootNodeId} " .
            "GROUP BY child.{$idColumn} " .
            "HAVING COUNT(child.{$idColumn}) " . ($includeParent ? '<' : '') . "= 1 " .
            "ORDER BY child.{$leftColumn} ASC";

        return $this->executeSelect($query, $fetchStyle, self::FETCH_MODE_ALL);
    }

    /**
     * Finds descendants of a node.
     * Adds a 'depth' column to each node
     *
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @param int $fetchStyle PDO fetch style
     * @param bool $includeParent Set to true to include the node whose descendants are being searched for in result
     * @return array
     */
    public function findDescendants($id, $table = null, $fetchStyle = self::FETCH_DEFAULT, $includeParent = false)
    {
        if (!is_int($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $table = $this->getTable($table);

        if (null === $table) {
            throw new Exception\NoTableSetException();
        }

        $table = $this->quoteName($table);
        $idColumn = $this->quoteName($this->getIdColumn());
        $leftColumn = $this->quoteName($this->getLeftColumn());
        $rightColumn = $this->quoteName($this->getRightColumn());

        $query = "SELECT child.* , COUNT(*) AS depth " .
            "FROM {$table} AS parent " .
            "JOIN (SELECT {$leftColumn} AS parentLft, {$rightColumn} AS parentRgt FROM {$table} WHERE {$idColumn} = {$id}) AS parent2 " .
            "JOIN {$table} AS child ON child.{$leftColumn} BETWEEN parent.{$leftColumn} AND parent.{$rightColumn} " .
            "WHERE parent.{$leftColumn} >" . ($includeParent ? '=' : '') . " parentLft " .
            "AND parent.{$rightColumn} <" . ($includeParent ? '=' : '') . " parentRgt " .
            " AND parent.{$idColumn} > {$this->rootNodeId} " .
            "GROUP BY child.{$idColumn} " .
            "ORDER BY child.{$leftColumn} ASC";

        return $this->executeSelect($query, $fetchStyle, self::FETCH_MODE_ALL);
    }

    /**
     * Find parent of a node
     * Proxies to findAncestors() with limit to return only first node found
     *
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @param int $fetchStyle PDO fetch style
     * @param bool $includeChild Set to true to include the node whose parent are searched for in result
     * @return mixed
     */
    public function findParent($id, $table = null, $fetchStyle = self::FETCH_DEFAULT, $includeChild = false)
    {
        return $this->findAncestors($id, $table, $fetchStyle, $includeChild, ($includeChild ? 2 : 1));
    }

    /**
     * Find ancestors of a node
     *
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @param int $fetchStyle PDO fetch style
     * @param bool $includeChild Set to true to include the node whose ancestors are searched for in result
     * @param int|null $limit Limit results to N of ancestors
     * @return mixed
     */
    public function findAncestors($id, $table = null, $fetchStyle = self::FETCH_DEFAULT, $includeChild = false, $limit = null)
    {
        if (!is_int($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $table = $this->getTable($table);

        if (null === $table) {
            throw new Exception\NoTableSetException();
        }

        $table = $this->quoteName($table);
        $idColumn = $this->quoteName($this->getIdColumn());
        $leftColumn = $this->quoteName($this->getLeftColumn());
        $rightColumn = $this->quoteName($this->getRightColumn());

        $query = "SELECT node.* " .
            "FROM {$table} AS node " .
            "JOIN (SELECT {$leftColumn} AS parentLft, {$rightColumn} AS parentRgt FROM {$table} WHERE {$idColumn} = {$id}) AS parent " .
            "WHERE node.{$leftColumn} <" . ($includeChild ? '=' : '') . " parentLft " .
            "AND node.{$rightColumn} >" . ($includeChild ? '=' : '') . " parentRgt " .
            "AND node.{$idColumn} > {$this->rootNodeId} " .
            "ORDER BY node.{$leftColumn} DESC";

        if (null !== $limit) {
            if (!is_int($limit)) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Limit must be null or integer. Instance of %s given',
                    is_object($id) ? get_class($id) : gettype($id)
                ));
            }
            $query .= " LIMIT {$limit}";
        }

        if ($limit === 1) {
            $fetchMode = self::FETCH_MODE_SINGLE;
        } else {
            $fetchMode = self::FETCH_MODE_ALL;
        }

        return $this->executeSelect($query, $fetchStyle, $fetchMode);
    }

    /**
     * Find siblings of a node
     *
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @param int $fetchStyle PDO fetch style
     * @param bool $includeCurrent Set to true to include the node whose siblings are searched for in result
     * @return array
     */
    public function findSiblings($id, $table = null, $fetchStyle = self::FETCH_DEFAULT, $includeCurrent = false)
    {
        if (!is_int($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $table = $this->getTable($table);

        if (null === $table) {
            throw new Exception\NoTableSetException();
        }

        $table = $this->quoteName($table);
        $idColumn = $this->quoteName($this->getIdColumn());
        $leftColumn = $this->quoteName($this->getLeftColumn());
        $rightColumn = $this->quoteName($this->getRightColumn());

        $query = "SELECT siblings.* " .
            "FROM {$table} as parent " .
            "JOIN (" .
            " SELECT {$leftColumn} AS parentLft, {$rightColumn} AS parentRgt " .
            " FROM {$table} AS parent " .
            " JOIN (" .
            "  SELECT {$leftColumn} AS nodeLft, {$rightColumn} as nodeRgt " .
            "  FROM {$table} " .
            "  WHERE {$idColumn} = {$id}" .
            " ) AS node " .
            " WHERE parent.lft < nodeLft AND parent.rgt > nodeRgt " .
            " ORDER BY parent.lft DESC " .
            " LIMIT 1 " .
            ") AS node_parent " .
            "JOIN {$table} AS siblings ON siblings.{$leftColumn} BETWEEN parent.{$leftColumn} AND parent.{$rightColumn} " .
            "WHERE parent.{$leftColumn} > parentLft AND parent.{$rightColumn} < parentRgt " .
            ($includeCurrent ? "" : "AND siblings.{$idColumn} <> {$id} ") .
            "GROUP BY siblings.{$idColumn} " .
            "HAVING COUNT(siblings.{$idColumn}) = 1 " .
            "ORDER BY siblings.{$leftColumn} ASC";

        return $this->executeSelect($query, $fetchStyle, NestedSet::FETCH_MODE_ALL);
    }

    /**
     * Find previous sibling of a node
     *
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @param int $fetchStyle PDO fetch style
     * @return mixed
     */
    public function findPrevSibling($id, $table = null, $fetchStyle = self::FETCH_DEFAULT)
    {
        if (!is_int($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $table = $this->getTable($table);

        if (null === $table) {
            throw new Exception\NoTableSetException();
        }

        $table = $this->quoteName($table);
        $idColumn = $this->quoteName($this->getIdColumn());
        $leftColumn = $this->quoteName($this->getLeftColumn());
        $rightColumn = $this->quoteName($this->getRightColumn());

        $query = "SELECT siblings.* " .
            "FROM {$table} AS siblings " .
            "JOIN (SELECT {$leftColumn} as nodeLft FROM {$table} WHERE {$idColumn} = {$id}) AS node " .
            "WHERE siblings.{$rightColumn} = nodeLft - 1 AND siblings.{$leftColumn} < nodeLft " .
            "ORDER BY {$leftColumn} ASC";

        return $this->executeSelect($query, $fetchStyle);
    }

    /**
     * Find next sibling of a node
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @param int $fetchStyle PDO fetch style
     * @return mixed
     */
    public function findNextSibling($id, $table = null, $fetchStyle = self::FETCH_DEFAULT)
    {
        if (!is_int($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $table = $this->getTable($table);

        if (null === $table) {
            throw new Exception\NoTableSetException();
        }

        $table = $this->quoteName($table);
        $idColumn = $this->quoteName($this->getIdColumn());
        $leftColumn = $this->quoteName($this->getLeftColumn());
        $rightColumn = $this->quoteName($this->getRightColumn());

        $query = "SELECT siblings.* " .
            "FROM {$table} AS siblings " .
            "JOIN (SELECT {$rightColumn} as nodeRgt FROM {$table} WHERE {$idColumn} = {$id}) AS node " .
            "WHERE siblings.{$leftColumn} = nodeRgt + 1 AND siblings.{$rightColumn} > nodeRgt " .
            "ORDER BY {$leftColumn} ASC";

        return $this->executeSelect($query, $fetchStyle);
    }

    /**
     * Find first child of a node
     *
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @param int $fetchStyle PDO fetch style
     * @return mixed
     */
    public function findFirstChild($id, $table = null, $fetchStyle = self::FETCH_DEFAULT)
    {
        if (!is_int($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $table = $this->getTable($table);

        if (null === $table) {
            throw new Exception\NoTableSetException();
        }

        $table = $this->quoteName($table);
        $idColumn = $this->quoteName($this->getIdColumn());
        $leftColumn = $this->quoteName($this->getLeftColumn());

        $query = "SELECT child.* " .
            "FROM {$table} AS child " .
            "JOIN (SELECT {$leftColumn} AS nodeLft FROM {$table} WHERE {$idColumn} = {$id}) AS node " .
            "WHERE child.{$leftColumn} = nodeLft + 1";

        return $this->executeSelect($query, $fetchStyle);
    }

    /**
     * Find last child of a node
     *
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @param int $fetchStyle PDO fetch style
     * @return mixed
     */
    public function findLastChild($id, $table = null, $fetchStyle = self::FETCH_DEFAULT)
    {
        if (!is_int($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $table = $this->getTable($table);

        if (null === $table) {
            throw new Exception\NoTableSetException();
        }

        $table = $this->quoteName($table);
        $idColumn = $this->quoteName($this->getIdColumn());
        $rightColumn = $this->quoteName($this->getRightColumn());

        $query = "SELECT child.* " .
            "FROM {$table} AS child " .
            "JOIN (SELECT {$rightColumn} AS nodeRgt FROM {$table} WHERE {$idColumn} = {$id}) AS node " .
            "WHERE child.{$rightColumn} = nodeRgt - 1";

        return $this->executeSelect($query, $fetchStyle);
    }
}