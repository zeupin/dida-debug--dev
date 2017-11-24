<?php
/**
 * Dida Framework  -- A Rapid Development Framework
 * Copyright (c) Zeupin LLC. (http://zeupin.com)
 *
 * Licensed under The MIT License.
 * Redistributions of files must retain the above copyright notice.
 */
require __DIR__ . '/bootstrap.php';

class A
{
    public $p1 = 1;
    public static $ps = "1234";
    public $p2 = 2;
    protected $b = 3;
}

$a = new A();

Dida\Debug\Debug::setPropFilter(true, true);
echo Dida\Debug\Debug::varDump($a);