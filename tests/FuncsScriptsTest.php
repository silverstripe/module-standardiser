<?php

use PHPUnit\Framework\TestCase;

class FuncsScriptsTest extends TestCase
{
    public function testPredictableRandomInt()
    {
        global $MODULE_DIR;
        $MODULE_DIR = 'lorem';
        $this->assertSame(0, predictable_random_int(15));
        $this->assertSame(25, predictable_random_int(30));
        $this->assertSame(45, predictable_random_int(30, 20));
        $MODULE_DIR = 'donuts';
        $this->assertSame(13, predictable_random_int(15));
        // use eval to simulate calling from a different file
        // it will suffix "(19) : eval()'d code" to the calling file in debug_backtrace()
        $ret = null;
        eval('$ret = predictable_random_int(15);');
        $this->assertSame(2, $ret);
    }
}
