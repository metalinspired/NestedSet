<?php
/**
 * Copyright (c) 2017 Milan Divkovic.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *  1. Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *  3. Neither the name of the copyright holder nor the names of its
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @author      Milan Divkovic <metalinspired@gmail.com>
 * @copyright   2017 Milan Divkovic.
 * @license     http://opensource.org/licenses/BSD-3-Clause
 */

namespace metalinspired\NestedSet;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Adapter\Driver\DriverInterface;
use Zend\Db\Adapter\Driver\Pdo\Pdo as ZendPdo;
use Zend\Db\Sql\Join;
use Zend\Db\Sql\Select;

class Config
{
    /**
     * @see AbstractNestedSet::$adapter
     * @var AdapterInterface
     */
    public $adapter = null;

    /**
     * @see AbstractNestedSet::$table
     * @var string|array
     */
    public $table = null;

    /**
     * @see AbstractNestedSet::$idColumn
     * @var string
     */
    public $idColumn = 'id';

    /**
     * @see AbstractNestedSet::$leftColumn
     * @var string
     */
    public $leftColumn = 'lft';

    /**
     * @see AbstractNestedSet::$rightColumn
     * @var string
     */
    public $rightColumn = 'rgt';

    /**
     * @see AbstractNestedSet::$rootNodeId
     * @var int|string|null
     */
    public $rootNodeId;

    /**
     * @see Find::$columns
     * @var array
     */
    public $columns = [Select::SQL_STAR];

    /**
     * @see Find::$includeSearchingNode
     * @var bool
     */
    public $includeSearchingNode = false;

    /**
     * @see Find::$joins
     * @var Join
     */
    public $joins = null;

    /**
     * @see Find::$depthLimit
     * @var int
     */
    public $depthLimit = null;

    public function __construct()
    {
        $this->joins = new Join();
    }

    /**
     * Creates Config object instance with database adapter created from provided DSN data
     *
     * @param string      $dsn
     * @param string|null $username
     * @param string|null $password
     * @return Config
     */
    public static function createWithDsn($dsn, $username = null, $password = null)
    {
        $pdo = new \PDO($dsn, $username, $password);

        return self::createWithPdo($pdo);
    }

    /**
     * Creates Config object instance with database adapter created with provided PDO instance
     *
     * @param \PDO $pdo
     * @return Config
     */
    public static function createWithPdo(\PDO $pdo)
    {
        $driver = new ZendPdo($pdo);

        return self::createWithDriver($driver);
    }

    /**
     * Creates Config object instance with database adapter created with provided Zend\DB\Adapter\Driver\* instance
     *
     * @param DriverInterface $driver
     * @return Config
     */
    public static function createWithDriver(DriverInterface $driver)
    {
        $adapter = new Adapter($driver);
        $config = new self();
        $config->adapter = $adapter;

        return $config;
    }

    /**
     * Returns instance of Factory
     *
     * @return Factory
     */
    public function getFactory()
    {
        return new Factory($this);
    }
}
