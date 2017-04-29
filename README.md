# metalinspired\NestedSet

## Introduction

This library allows you to manipulate records in database using [Nested set model](https://en.wikipedia.org/wiki/Nested_set_model)

Currently only MySql is supported

## Requirements

* PHP >= 5.6

## Instalation

```bash
$ composer require metalinspired\NestedSet
```

# Usage
## Manipulation
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
$rootNodeId = $nestedSet->createRootnode();
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
 *           to move them to new location because delete method
 *           will delete them as well
 */
$nestedSet->delete($childNode);
```