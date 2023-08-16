<?php

use PHPUnit\Framework\TestCase;

class ScriptsTest extends TestCase
{
    /**
     * This is a weird unit-test as it's essentially linting, though it's useful to ensure that the scripts are valid
     * 
     * Ensure that file_exists() isn't used any scripts as it it's a native PHP function that does not use $MODULE_DIR
     * Instead you need to use check_file_exists() which does use $MODULE_DIR
     */
    public function testNoFileExists()
    {
        $scripts = glob(__DIR__ . '/../scripts/**/*.php');
        foreach ($scripts as $script) {
            $contents = file_get_contents($script);
            $found = (bool) preg_match('#(?<!check_)file_exists\(#', $contents);
            $this->assertFalse($found, "Script $script has file_exists() in it, use check_file_exists() instead");
        }
        // need at least one assertion or phpunit says this is a risky test
        $this->assertTrue(true);
    }
}
