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

trait HybridNestedSetTrait
{
    /**
     * @var string
     */
    protected $parentColumn = 'parent';

    /**
     * @var string
     */
    protected $orderingColumn = 'ordering';

    /**
     * @var string
     */
    protected $depthColumn = 'depth';

    /**
     * @return string
     */
    public function getParentColumn()
    {
        return $this->parentColumn;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setParentColumn($name)
    {
        $this->parentColumn = $this->checkColumnName($name);
        $this->statements = [];

        return $this;
    }

    /**
     * @return string
     */
    public function getOrderingColumn()
    {
        return $this->orderingColumn;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setOrderingColumn($name)
    {
        $this->orderingColumn = $this->checkColumnName($name);
        $this->statements = [];

        return $this;
    }

    /**
     * @return string
     */
    public function getDepthColumn()
    {
        return $this->depthColumn;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setDepthColumn($name)
    {
        $this->depthColumn = $this->checkColumnName($name);
        $this->statements = [];

        return $this;
    }
}
