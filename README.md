# metalinspired\NestedSet v2

## Introduction

This library allows you to manipulate and retrieve database records using [nested set model](https://en.wikipedia.org/wiki/Nested_set_model) technique to represent tree data structure.
It uses Zend\Db as underlying database abstraction layer.

## Requirements

* PHP >= 5.6
* Zend\Db >= 2.8

## Installation

```bash
$ composer require metalinspired/nested-set
```

# Usage
## Config object

[Config](src/Config.php) class, as name suggests, is used to create an object with predefined configuration for manipulation/selection classes in this utility, and you do so by changing values of its public members.
It also has three static methods that create instance of Config object and set its *$adapter* member to an *Zend\DB\Adapter\Driver\\** instance.

Example:

```php
// Create Config object with DSN data
$config = Config::createWithDsn('mysql:dbname=some_database;host=localhost', 'some_user', 'some_password');
 
// Set table name
$config->table = 'some_table';
 
// If we don't want to retrieve all columns when using find* methods 
// we specify which columns we want to fetch
$config->columns = ['column1', 'column5', 'alias_of_column' => 'column7'];

// You can also instruct methods from Find class to include searching node in results
// For example, if you want to get children of node with id 5 including the node with id 5
$config->includeSearchingNode = true;
```

## Manipulation

[Manipulate](src/Manipulate.php) class contains methods for creating (inserting), moving and deleting nodes in nested set model.
It also has createRootNode method that creates a root node that serves as a container for all other nodes.

Example:
```php
// Create an instance of Manipulate class
$manipulate = new Manipulate($config);
 
// Create a root node on an empty table
$rootId = $manipulate->createRootNode();
 
// Create a node
$node1 = $manipulate->insert($rootId, ['column1' => 'some data', 'column2' => 'some more data']);
 
// Create another node that is child/leaf of first node
$node2 = $manipulate->insert($node, ['column1' => 'child data', 'column2' => 'some more child data']);
 
// Move node2 so it is on same level as node1
$manipulate->moveAfter($node2, $node1);
// Or we could have moved it in front of node1
$manipulate->moveBefore($node2, $node1);
 
// Move node2 back to its original position (as child of node1)
$manipulate->moveMakeChild($node2, $root1);
 
// Delete a node (and all its children, if any)
$manipulate->delete($node);
 
// You can also empty a node by delete all of its descendants
// or enter a node identifier as second parameter to move descendants to a new location
$manipulate->clean($parentNode, $destinationNode);
```

## Retrieving records

[Find](src/Find.php) class contains methods for retrieving records and their names pretty much explain what they do.
All you need is to provide them with node identifier.

```php
// Create instance of Find class
$find = new Find($config);
 
$find->findAncestors();
$find->findParent();
$find->findDescendants();
$find->findChildren();
$find->findFirstChild();
$find->findLastChild();
$find->findSiblings();
$find->findNextSibling();
$find->findPreviousSibling();
```