<?php

namespace metalinspired\NestedSet\Manipulate;

use metalinspired\NestedSet\Exception;

class Delete extends AbstractManipulate
{
    public function delete($id)
    {
        if (!is_int($id)) {
            throw new Exception\InvalidNodeIdentifierException($id);
        }

        /*
         * Get right and left values of node that is being deleted
         */
        $query = $this->buildQuery(
            "SELECT ::leftColumn:: AS lft, ::rightColumn:: AS rgt FROM ::table:: WHERE ::idColumn:: = {$id}",
            $table
        );

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
        $query = $this->buildQuery(
            "DELETE FROM ::table:: WHERE ::leftColumn:: >= {$nodeLeft} AND ::rightColumn:: <= {$nodeRight}",
            $table
        );

        $result = $this->executeDelete($query);

        /*
         * Close the gap left after deleting
         */
        $query = $this->buildQuery(
            "UPDATE ::table:: SET " .
            "::leftColumn:: = (CASE WHEN ::leftColumn:: > {$nodeRight} THEN ::leftColumn:: - {$size} ELSE ::leftColumn:: END), " .
            "::rightColumn:: = (CASE WHEN ::rightColumn:: > {$nodeRight} THEN ::rightColumn:: - {$size} ELSE ::rightColumn:: END) " .
            "WHERE ::rightColumn:: > {$nodeRight}",
            $table
        );

        $this->executeUpdate($query);

        return $result;
    }

    public function __invoke($id)
    {
        return $this->delete($id);
    }
}
