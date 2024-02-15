<?php

use Panlatent\CronExpressionDescriptor\ExpressionDescriptor;

// These functions in scripts can be used in scripts

/**
 * Check that a directory exists relative to the root of the module being processed
 *
 * Example usage:
 * check_dir_exists('src')
 */
function check_dir_exists($dirname) {
    global $MODULE_DIR;
    $path = "$MODULE_DIR/$dirname";
    if (!is_dir($path)) {
        info("Directory $path does not exist, though this should be OK");
        return false;
    }
    return true;
}

/**
 * Check that a file exists relative to the root of the module being processed
 *
 * Example usage:
 * check_file_exists('composer.json')
 */
function check_file_exists($filename)
{
    global $MODULE_DIR;
    $path = "$MODULE_DIR/$filename";
    if (!file_exists($path)) {
        info("File $path does not exist, though this should be OK");
        return false;
    }
    return true;
}

/**
 * Read a file relative to the root of the module being processed
 *
 * Example usage:
 * read_file('composer.json')
 */
function read_file($filename)
{
    global $MODULE_DIR;
    $path = "$MODULE_DIR/$filename";
    if (!file_exists($path)) {
        error("File $path does not exist");
    }
    return file_get_contents($path);
}

/**
 * Write a file relative to the root of the module being processed even if it already exists
 *
 * Example usage:
 * write_file_even_if_exists('.github/workflows/ci.yml')
 */
function write_file_even_if_exists($filename, $content)
{
    global $MODULE_DIR;
    $path = "$MODULE_DIR/$filename";
    write_file($path, $content);
}

/**
 * Write a file relative to the root of the module being processed only if it doesn't already exist
 *
 * Example usage:
 * write_file_if_not_exist('LICENSE')
 */
function write_file_if_not_exist($filename, $content)
{
    global $MODULE_DIR;
    $path = "$MODULE_DIR/$filename";
    if (!file_exists($path)) {
        write_file($path, $content);
    }
}

/**
 * Delete a file relative to the root of the module being processed if it exists
 *
 * Example usage:
 * delete_file_if_exists('.travis.yml')
 */
function delete_file_if_exists($filename)
{
    global $MODULE_DIR;
    $path = "$MODULE_DIR/$filename";
    if (file_exists($path)) {
        unlink($path);
        info("Deleted $path");
    }
}

/**
 * Rename a file relative to the root of the module being processed if it exists
 *
 * Example usage:
 * rename_file_if_exists('oldfilename.md', 'newfilename.md')
 */
function rename_file_if_exists($oldFilename, $newFilename)
{
    global $MODULE_DIR;
    $oldPath = "$MODULE_DIR/$oldFilename";
    $newPath = "$MODULE_DIR/$newFilename";
    if (file_exists($oldPath)) {
        $contents = read_file($oldFilename);
        write_file($newPath, $contents);
        delete_file_if_exists($oldFilename);
    }
}

/**
 * Determine if the module being processed is a recipe, including silverstripe-installer
 *
 * Example usage:
 * module_is_recipe()
 */
function module_is_recipe()
{
    if (!check_file_exists('composer.json')) {
        return false;
    }

    $contents = read_file('composer.json');
    $json = json_decode($contents);
    if (is_null($json)) {
        $lastError = json_last_error();
        error("Could not parse from composer.json - last error was $lastError");
    }

    return $json->type === 'silverstripe-recipe';
}

/**
 * Determine if the repository being processed is an actual silverstripe module e.g. silverstripe-admin, not gha-*
 *
 * Example usage:
 * is_module()
 */
function is_module()
{
    if (!check_file_exists('composer.json')) {
        return false;
    }

    $contents = read_file('composer.json');
    $json = json_decode($contents);
    if (is_null($json)) {
        $lastError = json_last_error();
        error("Could not parse from composer.json - last error was $lastError");
    }

    // config isn't technically a Silverstripe CMS module, but we treat it like one.
    if ($json->name === 'silverstripe/config') {
        return true;
    }

    $moduleTypes = [
        'silverstripe-vendormodule',
        'silverstripe-module',
        'silverstripe-recipe',
        'silverstripe-theme',
    ];
    return in_array($json->type, $moduleTypes);
}

/**
 * Determine if the module being processed is a theme
 *
 * Example usage:
 * is_theme()
 */
function is_theme()
{
    if (!check_file_exists('composer.json')) {
        return false;
    }

    $contents = read_file('composer.json');
    $json = json_decode($contents);
    if (is_null($json)) {
        $lastError = json_last_error();
        error("Could not parse from composer.json - last error was $lastError");
    }

    return $json->type === 'silverstripe-theme';
}

/**
 * Determine if the module being processed is a source of documentation
 *
 * Example usage:
 * is_docs()
 */
function is_docs()
{
    $moduleName = module_name();
    return $moduleName === 'developer-docs' || $moduleName === 'silverstripe-userhelp-content';
}

/**
 * Determine if the module being processed is a gha-* repository e.g. gha-ci
 *
 * Example usage:
 * is_gha_repository()
 */
function is_gha_repository()
{
    global $MODULE_DIR;
    return !is_module() && strpos($MODULE_DIR, '/gha-') !== false;
}

/**
 * Return the module name without the account e.g. silverstripe/silverstripe-admin with return silverstripe-admin
 *
 * Example usage:
 * if (module_name() === 'silverstripe-admin) {
 *     // logic
 * }
 */
function module_name()
{
    global $MODULE_DIR;
    $parts = explode('/', $MODULE_DIR);
    return end($parts);
}

/**
 * Determine if the module being processed is one of the modules in a list
 *
 * Example usage:
 * module_is_one_of(['silverstripe-mfa', 'silverstripe-totp'])
 */
function module_is_one_of($repos)
{
    global $MODULE_DIR;
    if (!is_array($repos)) {
        error('repos is not an array');
    }
    foreach ($repos as $repo) {
        if (!is_string($repo)) {
            error('repo is not a string');
        }
        if (strpos($MODULE_DIR, "/$repo") !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Return the github account of the module being processed
 *
 * Example usage:
 * module_account()
 */
function module_account()
{
    $s = read_file('.git/config');
    if (!preg_match('#github.com:([^/]+)/#', $s, $matches)) {
        error('Could not determine github account');
    }
    return $matches[1];
}

/**
 * Output an info message to the console
 *
 * Example usage:
 * info('This is a mildly interesting message')
 */
function info($message)
{
    // using writeln with <info> instead of ->info() so that it only takes up one line instead of five
    io()->writeln("<info>$message</>");
}

/**
 * Output a warning message to the console
 *
 * Example usage:
 * warning('This is something you might want to pay attention to')
 */
function warning($message)
{
    io()->warning($message);
}

/**
 * Converts a cron expression to a human readable string
 * Says UTC because that's what GitHub Actions uses
 *
 * Example usage:
 * human_cron('5 4 * * 0')
 * => 'At 4:05 AM UTC, only on Sunday'
 */
function human_cron(string $cron): string
{
    $str = (new ExpressionDescriptor($cron))->getDescription();
    $str = preg_replace('#0([1-9]):#', '$1:', $str);
    $str = preg_replace('# (AM|PM),#', ' $1 UTC,', $str);
    return $str;
}

/**
 * Creates a predicatable random int between 0 and $max based on the module name and file name script
 * is called with to be used with the % mod operator.
 * $offset variable will offset both the min (0) and $max. e.g. $offset of 1 with a max of 27 will return an int
 * between 1 and 28
 * Note that this will return the exact same value every time it is called for a given filename in a given module
 */
function predictable_random_int($max, $offset = 0): int
{
    global $MODULE_DIR;
    $callingFile = debug_backtrace()[0]['file'];
    // remove absolute path e.g. /home/myuser/...
    $moduleStandardiserDir = basename(__DIR__);
    $dirQuoted = preg_quote($moduleStandardiserDir);
    // double $dirQuoted is for github actions CI where there which will have a directory strcture
    // with /module-standardiser/module-standardiser/...
    if (!preg_match("#/$dirQuoted/$dirQuoted/(.+)$#", $callingFile, $matches)) {
        preg_match("#/$dirQuoted/(.+)$#", $callingFile, $matches);
    }
    $relativePath = $matches[1];
    $chars = str_split("$MODULE_DIR-$relativePath");
    $codes = array_map(fn($c) => ord($c), $chars);
    mt_srand(array_sum($codes));
    return mt_rand(0, $max) + $offset;
}
