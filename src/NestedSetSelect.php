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
     * @param int $id
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
     * {@inheritdoc}
     */
    public function setOptions(array $options)
    {
        parent::setOptions($options);

        if (array_key_exists('root_node_id', $options)) {
            $this->setRootNodeId($options['root_node_id']);
        }
    }

    /**
     * Find immediate children of a node
     * Proxies to findDescendants() with limit to return only first level of descendant nodes
     *
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @param array|null $columns Array containing names of columns to fetch or null to fetch all columns. Keys can be used as aliases
     * @param int $fetchStyle PDO fetch style
     * @param bool $includeParent Set to true to include the node whose children are being searched for in result
     * @return array
     */
    public function findChildren($id, $table = null, $columns = null, $fetchStyle = self::FETCH_DEFAULT, $includeParent = false)
    {
        return $this->findDescendants($id, $table, $columns, $fetchStyle, $includeParent, 1);
    }

    /**
     * Finds descendants of a node.
     * If limit is not set or limit is greater than 1 adds a 'depth' column to each node
     *
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @param array|null $columns Array containing names of columns to fetch or null to fetch all columns. Keys can be used as aliases
     * @param int $fetchStyle PDO fetch style
     * @param bool $includeParent Set to true to include the node whose descendants are being searched for in result
     * @param int|null $limit If set returns descendants deep as limit
     * @return array
     */
    public function findDescendants($id, $table = null, $columns = null, $fetchStyle = self::FETCH_DEFAULT, $includeParent = false, $limit = null)
    {
        if (!is_int($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $query = $this->buildQuery(
            "SELECT ::columns::" . (null === $limit || $limit > 1 ? ", q.depth AS depth " : " ") .
            "FROM (" .
            " SELECT child.::idColumn::" . (null === $limit || $limit > 1 ? ", (CASE WHEN child.::idColumn:: = {$id} THEN 0 ELSE COUNT(*) END) AS depth" : "") .
            " FROM ::table:: AS head_parent" .
            " JOIN ::table:: AS parent ON (" .
            "  parent.::leftColumn:: >= head_parent.::leftColumn::" .
            "  AND parent.::rightColumn:: < head_parent.::rightColumn::" .
            " ) " .
            " JOIN ::table:: AS child ON (" .
            "  child.::leftColumn:: BETWEEN parent.::leftColumn:: AND parent.::rightColumn::" .
            " )" .
            " WHERE head_parent.::idColumn:: = {$id}" .
            "  AND parent.::idColumn:: > {$this->rootNodeId}" .
            ($includeParent ? " OR child.::idColumn:: = {$id}" : "") .
            " GROUP BY child.::idColumn::" .
            " HAVING count(*) >= 1" .
            ($limit ? " AND count(*) <= {$limit}" : "") .
            ") AS q " .
            "JOIN ::table:: AS t ON t.::idColumn:: = q.::idColumn:: " .
            "ORDER BY t.::leftColumn:: ASC",
            $table,
            ['::columns::' => $this->buildColumnsString('t', $columns)]
        );

        return $this->executeSelect($query, $fetchStyle, self::FETCH_MODE_ALL);
    }

    /**
     * Find parent of a node
     * Proxies to findAncestors() with limit to return only first node found
     *
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @param array|null $columns Array containing names of columns to fetch or null to fetch all columns. Keys can be used as aliases
     * @param int $fetchStyle PDO fetch style
     * @param bool $includeChild Set to true to include the node whose parent are searched for in result
     * @return mixed
     */
    public function findParent($id, $table = null, $columns = null, $fetchStyle = self::FETCH_DEFAULT, $includeChild = false)
    {
        return $this->findAncestors($id, $table, $columns, $fetchStyle, $includeChild, ($includeChild ? 2 : 1));
    }

    /**
     * Find ancestors of a node
     *
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @param array|null $columns Array containing names of columns to fetch or null to fetch all columns. Keys can be used as aliases
     * @param int $fetchStyle PDO fetch style
     * @param bool $includeChild Set to true to include the node whose ancestors are searched for in result
     * @param int|null $limit Limit results to N of ancestors
     * @return mixed
     */
    public function findAncestors($id, $table = null, $columns = null, $fetchStyle = self::FETCH_DEFAULT, $includeChild = false, $limit = null)
    {
        if (!is_int($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $sqlTop = "";
        $sqlLimit = "";

        if (null !== $limit) {
            if (!is_int($limit)) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Limit must be null or integer. Instance of %s given',
                    is_object($id) ? get_class($id) : gettype($id)
                ));
            }
            if ('dblib' === $this->driverName) {
                $sqlTop = "TOP {$limit}";
            } else {
                $sqlLimit = " LIMIT {$limit}";
            }
        }

        $query = "SELECT {$sqlTop} ::columns:: " .
            "FROM (" .
            " SELECT ::leftColumn::, ::rightColumn::" .
            " FROM ::table::" .
            " WHERE ::idColumn:: = {$id}" .
            ") AS parent " .
            "JOIN ::table:: AS t ON (" .
            " t.::leftColumn:: <" . ($includeChild ? "=" : "") . " parent.::leftColumn::" .
            " AND t.::rightColumn:: >" . ($includeChild ? "=" : "") . " parent.::rightColumn::" .
            " AND t.::idColumn:: > {$this->rootNodeId}" .
            ") " .
            "ORDER BY t.::leftColumn:: DESC" .
            $sqlLimit;

        if ($limit === 1) {
            $fetchMode = self::FETCH_MODE_SINGLE;
        } else {
            $fetchMode = self::FETCH_MODE_ALL;
        }

        $query = $this->buildQuery($query, $table, ['::columns::' => $this->buildColumnsString('t', $columns)]);

        return $this->executeSelect($query, $fetchStyle, $fetchMode);
    }

    /**
     * Find siblings of a node
     *
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @param array|null $columns Array containing names of columns to fetch or null to fetch all columns. Keys can be used as aliases
     * @param int $fetchStyle PDO fetch style
     * @param bool $includeCurrent Set to true to include the node whose siblings are searched for in result
     * @return array
     */
    public function findSiblings($id, $table = null, $columns = null, $fetchStyle = self::FETCH_DEFAULT, $includeCurrent = false)
    {
        if (!is_int($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $sqlTop = "";
        $sqlLimit = "";

        if ('dblib' === $this->driverName) {
            $sqlTop = "TOP 1";
        } else {
            $sqlLimit = " LIMIT 1";
        }

        $query = $this->buildQuery(
            "SELECT ::columns:: " .
            "FROM (" .
            " SELECT child.::idColumn::" .
            " FROM ::table:: node" .
            " JOIN ::table:: AS head_parent ON head_parent.::idColumn:: = (" .
            "  SELECT {$sqlTop} ::idColumn::" .
            "  FROM ::table::" .
            "  WHERE node.::leftColumn:: > ::leftColumn:: AND node.::rightColumn:: < ::rightColumn::" .
            "  ORDER BY ::table::.::leftColumn:: DESC" .
            "  {$sqlLimit}" .
            " )" .
            " JOIN ::table:: parent ON (parent.::leftColumn:: >= head_parent.::leftColumn:: AND parent.::rightColumn:: < head_parent.::rightColumn::)" .
            " JOIN ::table:: child ON (child.::leftColumn:: BETWEEN parent.::leftColumn:: AND parent.::rightColumn::)" .
            " WHERE node.::idColumn:: = {$id}" . ($includeCurrent ? "" : " AND child.::idColumn:: <> {$id}") .
            " GROUP BY child.::idColumn::" .
            " HAVING count(*) = 1" .
            ") AS q " .
            "JOIN ::table:: t ON t.::idColumn:: = q.::idColumn::" . "
            ORDER BY t.::leftColumn:: ASC",
            $table,
            ['::columns::' => $this->buildColumnsString('t', $columns)]
        );

        return $this->executeSelect($query, $fetchStyle, NestedSet::FETCH_MODE_ALL);
    }

    /**
     * Find next sibling of a node
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @param array|null $columns Array containing names of columns to fetch or null to fetch all columns. Keys can be used as aliases
     * @param int $fetchStyle PDO fetch style
     * @return mixed
     */
    public function findNextSibling($id, $table = null, $columns = null, $fetchStyle = self::FETCH_DEFAULT)
    {
        if (!is_int($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $query = $this->buildQuery(
            "SELECT ::columns:: " .
            "FROM ::table:: AS t " .
            "JOIN ::table:: AS node ON node.::idColumn:: = {$id} " .
            "WHERE t.::leftColumn:: = node.::rightColumn:: + 1 " .
            "ORDER BY t.::leftColumn:: ASC",
            $table,
            ['::columns::' => $this->buildColumnsString('t', $columns)]
        );

        return $this->executeSelect($query, $fetchStyle);
    }

    /**
     * Find previous sibling of a node
     *
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @param array|null $columns Array containing names of columns to fetch or null to fetch all columns. Keys can be used as aliases
     * @param int $fetchStyle PDO fetch style
     * @return mixed
     */
    public function findPrevSibling($id, $table = null, $columns = null, $fetchStyle = self::FETCH_DEFAULT)
    {
        if (!is_int($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $query = $this->buildQuery(
            "SELECT ::columns:: " .
            "FROM ::table:: AS t " .
            "JOIN ::table:: AS node ON node.::idColumn:: = {$id} " .
            "WHERE t.::rightColumn:: = node.::leftColumn:: - 1 " .
            "ORDER BY t.::leftColumn:: ASC",
            $table,
            ['::columns::' => $this->buildColumnsString('t', $columns)]
        );

        return $this->executeSelect($query, $fetchStyle);
    }

    /**
     * Find first child of a node
     *
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @param array|null $columns Array containing names of columns to fetch or null to fetch all columns. Keys can be used as aliases
     * @param int $fetchStyle PDO fetch style
     * @return mixed
     */
    public function findFirstChild($id, $table = null, $columns = null, $fetchStyle = self::FETCH_DEFAULT)
    {
        if (!is_int($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $query = $this->buildQuery(
            "SELECT ::columns:: " .
            "FROM ::table:: AS t " .
            "JOIN ::table:: AS node ON node.::idColumn:: = {$id} " .
            "WHERE t.::leftColumn:: = node.::leftColumn:: + 1",
            $table,
            ['::columns::' => $this->buildColumnsString('t', $columns)]
        );

        return $this->executeSelect($query, $fetchStyle);
    }

    /**
     * Find last child of a node
     *
     * @param int $id Node identifier
     * @param string|null $table Table name
     * @param array|null $columns Array containing names of columns to fetch or null to fetch all columns. Keys can be used as aliases
     * @param int $fetchStyle PDO fetch style
     * @return mixed
     */
    public function findLastChild($id, $table = null, $columns = null, $fetchStyle = self::FETCH_DEFAULT)
    {
        if (!is_int($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        $query = $this->buildQuery(
            "SELECT ::columns:: " .
            "FROM ::table:: AS t " .
            "JOIN ::table:: AS node ON node.::idColumn:: = {$id} " .
            "WHERE t.::rightColumn:: = node.::rightColumn:: - 1",
            $table,
            ['::columns::' => $this->buildColumnsString('t', $columns)]
        );

        return $this->executeSelect($query, $fetchStyle);
    }

    /**
     * Converts an array into string with column names and, optionally, aliases that are to be fetched from table
     *
     * @param string $table Table name
     * @param array|null $columns Array containing names of columns to fetch or null to fetch all columns. Keys can be used as aliases
     * @return string
     */
    protected function buildColumnsString($table, $columns)
    {

        if (null === $table || !is_string($table) || empty($table)) {
            throw new Exception\InvalidArgumentException('Invalid table name');
        }

        if (!is_array($columns) && null !== $columns && '*' !== $columns && !empty($columns)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Columns must be array, null, or \'*\' string. Instance of %s given',
                is_object($columns) ? get_class($columns) : gettype($columns)
            ));
        }

        $table = $this->quoteName($table);

        if (null === $columns || '*' === $columns || empty($columns)) {
            $columnsString = $table . '.*';
        } else {
            foreach ($columns as $key => &$value) {
                if (!is_string($value) || empty($value)) {
                    throw new Exception\RuntimeException('Invalid column name');
                }

                $column = $this->quoteName($value);
                $value = $table . '.' . $column . ' AS ';

                if (is_string($key)) {
                    $value .= $this->quoteName($key);
                } else {
                    $value .= $column;
                }
            }

            $columnsString = implode(', ', $columns);
        }

        return $columnsString;
    }
}