<?php

use PHPUnit\Framework\TestCase;

class FuncsScriptsTest extends TestCase
{
    public function testPredictableRandomInt()
    {
        global $GITHUB_REF;
        // set $GITHUB_REF because by module_name() which is used by predictable_random_int()
        $GITHUB_REF = 'myaccount/lorem';
        $this->assertSame(1, predictable_random_int('test-script', 15));
        // Setting a higher max does more than just add to the result, it's somewhat random
        $this->assertSame(23, predictable_random_int('test-script', 30));
        // Setting an offset simply adds to the result of the same max as above
        $this->assertSame(43, predictable_random_int('test-script', 30, 20));
        // Changing $GITHUB_REF will change the result
        $GITHUB_REF = 'myaccount/donuts';
        $this->assertSame(15, predictable_random_int('test-script', 15));
        // Changing the script name will change the result
        $this->assertSame(6, predictable_random_int('different-script', 15));
    }
}
