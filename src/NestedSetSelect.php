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
     * Proxies to findDescendants() with limit to return only first level of descendant nodes
     *
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @param int $fetchStyle PDO fetch style
     * @param bool $includeParent Set to true to include the node whose children are being searched for in result
     * @return array
     */
    public function findChildren($id, $table = null, $fetchStyle = self::FETCH_DEFAULT, $includeParent = false)
    {
        return $this->findDescendants($id, $table, $fetchStyle, $includeParent, 1);
    }

    /**
     * Finds descendants of a node.
     * If limit is not set or limit is greater than 1 adds a 'depth' column to each node
     *
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @param int $fetchStyle PDO fetch style
     * @param bool $includeParent Set to true to include the node whose descendants are being searched for in result
     * @param int|null $limit If set returns descendants deep as limit
     * @return array
     */
    public function findDescendants($id, $table = null, $fetchStyle = self::FETCH_DEFAULT, $includeParent = false, $limit = null)
    {
        if (!is_int($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $table = $this->getTable($table);

        if (null === $table) {
            throw new Exception\NoTableSetException();
        }

        if (null !== $limit && !is_int($limit)) {
            throw new Exception\InvalidArgumentException(sprintf(
                "Limit can be null or integer. Instance of %s given",
                is_object($limit) ? get_class($limit) : gettype($limit)
            ));
        }

        $table = $this->quoteName($table);
        $idColumn = $this->quoteName($this->getIdColumn());
        $leftColumn = $this->quoteName($this->getLeftColumn());
        $rightColumn = $this->quoteName($this->getRightColumn());

        $query = "SELECT t.*" . (null === $limit || $limit > 1 ? ", q.depth AS depth " : " ") .
            "FROM (" .
            " SELECT child.{$idColumn}" . (null === $limit || $limit > 1 ? ", (CASE WHEN child.{$idColumn} = {$id} THEN 0 ELSE COUNT(*) END) AS depth" : "") .
            " FROM {$table} AS head_parent" .
            " JOIN {$table} AS parent ON (" .
            "  parent.{$leftColumn} >= head_parent.{$leftColumn}" .
            "  AND parent.{$rightColumn} < head_parent.{$rightColumn}" .
            " ) " .
            " JOIN {$table} AS child ON (" .
            "  child.{$leftColumn} BETWEEN parent.{$leftColumn} AND parent.{$rightColumn}" .
            " )" .
            " WHERE head_parent.{$idColumn} = {$id}" .
            "  AND parent.{$idColumn} > {$this->rootNodeId}" .
            ($includeParent ? " OR child.{$idColumn} = {$id}" : "") .
            " GROUP BY child.{$idColumn}" .
            " HAVING count(*) >= 1" .
            ($limit ? " AND count(*) <= {$limit}" : "") .
            ") AS q " .
            "JOIN {$table} AS t ON t.{$idColumn} = q.{$idColumn} " .
            "ORDER BY t.{$leftColumn} ASC";

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

        $sqlTop = "";
        $sqlLimit = "";

        if (null !== $limit) {
            if (!is_int($limit)) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Limit must be null or integer. Instance of %s given',
                    is_object($id) ? get_class($id) : gettype($id)
                ));
            }
            if ('dblib' === $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
                $sqlTop = "TOP {$limit}";
            } else {
                $sqlLimit = " LIMIT {$limit}";
            }
        }

        $query = "SELECT {$sqlTop} node.* " .
            "FROM (" .
            " SELECT {$leftColumn}, {$rightColumn}" .
            " FROM {$table}" .
            " WHERE {$idColumn} = {$id}" .
            ") AS parent " .
            "JOIN {$table} AS node ON (" .
            " node.{$leftColumn} < parent.{$leftColumn}" .
            " AND node.{$rightColumn} > parent.{$rightColumn}" .
            " AND node.{$idColumn} > {$this->rootNodeId}" .
            ") " .
            "ORDER BY node.{$leftColumn} DESC" .
            $sqlLimit;

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

        $sqlTop = "";
        $sqlLimit = "";

        if ('dblib' === $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
            $sqlTop = "TOP 1";
        } else {
            $sqlLimit = " LIMIT 1";
        }

        $query = "SELECT t.* " .
            "FROM (" .
            " SELECT child.{$idColumn}" .
            " FROM {$table} node" .
            " JOIN {$table} AS head_parent ON head_parent.{$idColumn} = (" .
            "  SELECT {$sqlTop} {$idColumn}" .
            "  FROM {$table}" .
            "  WHERE node.{$leftColumn} > {$leftColumn} AND node.{$rightColumn} < {$rightColumn}" .
            "  ORDER BY {$table}.{$leftColumn} DESC" .
            "  {$sqlLimit}" .
            " )" .
            " JOIN {$table} parent ON (parent.{$leftColumn} >= head_parent.{$leftColumn} AND parent.{$rightColumn} < head_parent.{$rightColumn})" .
            " JOIN {$table} child ON (child.{$leftColumn} BETWEEN parent.{$leftColumn} AND parent.{$rightColumn})" .
            " WHERE node.{$idColumn} = {$id}" . ($includeCurrent ? "" : " AND child.{$idColumn} <> {$id}") .
            " GROUP BY child.{$idColumn}" .
            " HAVING count(*) = 1" .
            ") q " .
            "JOIN {$table} t ON t.{$idColumn} = q.{$idColumn}" . "
            ORDER BY t.{$leftColumn} ASC";

        return $this->executeSelect($query, $fetchStyle, NestedSet::FETCH_MODE_ALL);
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

        $query = "SELECT sibling.* " .
            "FROM {$table} AS sibling " .
            "JOIN {$table} AS node ON node.{$idColumn} = {$id} " .
            "WHERE sibling.{$leftColumn} = node.{$rightColumn} + 1 AND sibling.{$rightColumn} > node.{$rightColumn} " .
            "ORDER BY {$leftColumn} ASC";

        return $this->executeSelect($query, $fetchStyle);
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

        $query = "SELECT sibling.* " .
            "FROM {$table} AS sibling " .
            "JOIN {$table} AS node ON node.{$idColumn} = {$id} " .
            "WHERE sibling.{$rightColumn} = node.{$leftColumn} - 1 AND sibling.{$leftColumn} < node.{$leftColumn} " .
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
            "JOIN {$table} AS node ON node.{$idColumn} = {$id} " .
            "WHERE child.{$leftColumn} = node.{$leftColumn} + 1";

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
            "JOIN {$table} AS node ON node.{$idColumn} = {$id} " .
            "WHERE child.{$rightColumn} = node.{$rightColumn} - 1";

        return $this->executeSelect($query, $fetchStyle);
    }
}