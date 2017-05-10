# metalinspired\NestedSet

## Introduction

This library allows you to manipulate and retrieve database records using [nested set model](https://en.wikipedia.org/wiki/Nested_set_model) technique to represent tree data structure.
 
Tested on MySQL, SQLite and MS SQL Server.

## Requirements

* PHP >= 5.6

## Installation

```bash
$ composer require metalinspired/nested-set
```

# Usage
## Manipulation - NestedSet class
### Creating instance

```php
<?php
 
use metalinspired\NestedSet\NestedSet;
 
$pdo = new \PDO('mysql:dbname=database;host=localhost', 'user', 'password');
 
/*
 * You can specify table to be used as second parameter when creating instance
 * or pass table name to each of manipulating functions
 */
$nestedSet = new NestedSet($pdo, 'table');
```

### Creating root node

```php
/*
 * Warning: Call this method only on empty table and only once!
 */
$rootNodeId = $nestedSet->createRootNode();
```

### Inserting node

```php
/*
 * Create a node (set foo column value to bar) 
 * and than add a child to it (set foo column value to baz)
 */
$nodeId = $nestedSet->insert(['foo' => 'bar'], $rootNodeId);
$childNodeId = $nestedSet->insert(['foo' => 'baz'], $nodeId);
```

### Moving node

```php
/*
 * Move child node to behind first node
 * Now both nodes are on same level
 */
$nestedSet->move($childNode, $nodeId, $nestedSet::MOVE_AFTER);
```

### Deleting node

```php
/*
 * Delete a child node (that no longer is child node actually)
 * Note: If node has children/leafs it is your responsibility
 *       to move them to new location because delete method
 *       will delete them as well
 */
$nestedSet->delete($childNode);
```

## Retrieving records - NestedSetSelect class
### Creating instance

```php
<?php
 
use metalinspired\NestedSet\NestedSetSelect;
 
$pdo = new \PDO('mysql:dbname=database;host=localhost', 'user', 'password');
 
/*
 * You can specify table to be used as second parameter when creating instance
 * or pass table name to each of manipulating functions
 */
$nestedSet = new NestedSetSelect($pdo, 'table');
```

### Finding descendants

```php
/*
 * Find imediate children of a node
 */
$nestedSet->findChildren()
 
/*
 * Find all descendants of a node
 */
$nestedSet->findDescendants()
 
/*
 * Find first child of a node
 */
$nestedSet->findFirstChild()
 
/*
 * Find last child of a node
 */
$nestedSet->findLastChild()
```

### Finding ancestors

```php
/*
 * Find parent of a node
 */
$nestedSet->findParent()
 
/*
 * Find all ancestors of a node
 */
$nestedSet->findAncestors()
```

### Finding siblings

```php
/*
 * Find siblings of a node
 */
$nestedSet->findSiblings()
 
/*
 * Find previous sibbling of a node
 */
$nestedSet->findPrevSibling()
 
/*
 * Find next sibbling of a node
 */
$nestedSet->findNextSibling()
```

**Note: Please check methods description as I am to lazy to write all here :)**

# TODO
- [x] add support for SQLite
- [x] add support for MS SQL Server
- [x] ability to choose which columns to fetch with find methods
- [ ] ability to join on other tables with find methods
- [ ] support for hybrid nested set