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
 * @license     http://opensource.org/licenses/BSD-license FreeBSD License
 */

/*
 * Set error reporting
 */
error_reporting(E_ALL | E_STRICT);

if (class_exists('\PHPUnit\Runner\Version', true)) {
    $phpUnitVersion = \PHPUnit\Runner\Version::id();
    if ('@package_version@' !== $phpUnitVersion && version_compare($phpUnitVersion, '6.1.0', '<')) {
        echo 'This version of PHPUnit (' . \PHPUnit\Runner\Version::id() . ') is not supported'
            . ' in the zend-db unit tests. Supported is version 4.0.0 or higher.'
            . ' See also the CONTRIBUTING.md file in the component root.' . PHP_EOL;
        exit(1);
    }
    unset($phpUnitVersion);
}

/*
 * Setup autoloading
 */
require __DIR__ . '/../vendor/autoload.php';
