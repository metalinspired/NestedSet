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

use metalinspired\NestedSet\Exception\InvalidArgumentException;

/**
 * Class Factory
 *
 * @property Find             $find
 * @property Manipulate       $manipulate
 * @property HybridFind       $hybridFind
 * @property HybridManipulate $hybridManipulate
 */
class Factory
{
    /**
     * Configuration object used for creating classes
     *
     * @var Config
     */
    protected $config;

    /**
     * @var Find
     */
    protected $find;

    /**
     * @var Manipulate
     */
    protected $manipulate;

    /**
     * @var HybridFind
     */
    protected $hybridFind;

    /**
     * @var HybridManipulate
     */
    protected $hybridManipulate;

    /**
     * Factory constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = clone $config;
    }

    /**
     * Variable overloading
     *
     * @param  string $name
     * @throws InvalidArgumentException
     * @return mixed
     */
    public function __get($name)
    {
        switch (strtolower($name)) {
            case 'find':
                return $this->find();
            case 'manipulate':
                return $this->manipulate();
            case 'hybridfind':
                return $this->hybridFind();
            case 'hybridmanipulate':
                return $this->hybridManipulate();
            default:
                throw new InvalidArgumentException('Not a valid magic property for this object');
        }
    }

    /**
     * Loads new configuration
     *
     * @param Config $config
     */
    public function loadConfig(Config $config)
    {
        $this->config = clone $config;

        foreach (get_object_vars($this) as $object) {
            if ($object && method_exists($object, 'loadConfig')) {
                $object->loadConfig($config);
            }
        }
    }

    /**
     * Get Find class
     *
     * @return Find
     */
    public function find()
    {
        if (! $this->find) {
            $this->find = new Find($this->config);
        }

        return $this->find;
    }

    /**
     * Get Manipulate class
     *
     * @return Manipulate
     */
    public function manipulate()
    {
        if (! $this->manipulate) {
            $this->manipulate = new Manipulate($this->config);
        }

        return $this->manipulate;
    }

    /**
     * Get HybridFind class
     *
     * @return HybridFind
     */
    public function hybridFind()
    {
        if (! $this->hybridFind) {
            $this->hybridFind = new HybridFind($this->config);
        }

        return $this->hybridFind;
    }

    /**
     * Get HybridManipulate class
     *
     * @return HybridManipulate
     */
    public function hybridManipulate()
    {
        if (! $this->hybridManipulate) {
            $this->hybridManipulate = new HybridManipulate($this->config);
        }

        return $this->hybridManipulate;
    }
}
