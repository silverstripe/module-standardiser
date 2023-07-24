<?php

use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

// THESE FUNCTIONS ARE NOT INTENDED FOR USE IN SCRIPT FILES - only use functions in funcs_scripts.php

/**
 * Output an error to the console and halt progress
 * 
 * error() is not intended for use in scripts specificially because it will halt progress
 * Instead scripts should be more tolerant though use of functions like check_file_exists() or just
 * using warning() instead which output a big warning in the console while still continuing
 */
function error($message)
{
    output_prs_created();
    output_repos_with_prs_created();
    io()->error($message);
    if (!running_unit_tests()) {
        die;
    }
}

/**
 * Write to a file after trimming the contents and adding a newline
 */
function write_file($path, $contents)
{
    if (empty($path)) {
        error('Path cannot be empty');
    }
    $dirname = dirname($path);
    if (!file_exists($dirname)) {
        error("Directory $dirname does not exist");
    }
    $contents = trim($contents) . "\n";
    file_put_contents($path, $contents);
    info("Wrote to $path");
}

/**
 * Returns all the supported modules for a particular cms major version
 * Will download the list if it doesn't exist
 */
function supported_modules($cmsMajor)
{
    $filename = "_data/modules-cms$cmsMajor.json";
    if (!file_exists($filename)) {
        $url = "https://raw.githubusercontent.com/silverstripe/supported-modules/$cmsMajor/modules.json";
        info("Downloading $url to $filename");
        $contents = file_get_contents($url);
        file_put_contents($filename, $contents);
    }
    $json = json_decode(file_get_contents($filename), true);
    if (is_null($json)) {
        $lastError = json_last_error();
        error("Could not parse from $filename - last error was $lastError");
    }
    $modules = [];
    foreach ($json as $module) {
        $ghrepo = $module['github'];
        $modules[] = [
            'ghrepo' => $ghrepo,
            'account' => explode('/', $ghrepo)[0],
            'repo' => explode('/', $ghrepo)[1],
            'cloneUrl' => "git@github.com:$ghrepo.git",
            'branch' => max($module['branches'] ?: [-1])
        ];
    }
    return $modules;
}

/**
 * Returns a list of all scripts files to run against a particular cms major version
 */
function script_files($cmsMajor)
{
    if (!ctype_digit($cmsMajor)) {
        $cmsMajor = "-$cmsMajor";
    }
    $scriptFiles = [];
    $dir = "scripts/cms$cmsMajor";
    if (!file_exists($dir)) {
        warning("$dir does not exist, no CMS $cmsMajor specific scripts will be run");
        return $scriptFiles;
    }
    if (!is_dir($dir)) {
        error("$dir is not a directory");
    }
    if ($handle = opendir($dir)) {
        while (false !== ($scriptFile = readdir($handle))) {
            if ('.' === $scriptFile || '..' === $scriptFile) {
                continue;
            }
            $scriptFiles[] = "$dir/$scriptFile";
        }
        closedir($handle);
    }
    return $scriptFiles;
}

/**
 * Runs a shell command and returns the output
 */
function cmd($cmd, $cwd)
{
    info("Running command: $cmd in $cwd");
    // using Process::fromShellCommandline() instead of new Process() so that pipes work
    $process = Process::fromShellCommandline($cmd, $cwd);
    $process->run();
    if (!$process->isSuccessful()) {
        warning("Error running command: $cmd in $cwd");
        error("Output was: " . $process->getErrorOutput());
    }
    return trim($process->getOutput());
}

/**
 * Returns a object used to output to the console
 */
function io(): SymfonyStyle
{
    global $IN, $OUT;
    return new SymfonyStyle($IN ?: new ArgvInput(), $OUT ?: new NullOutput);
}

/**
 * Removes a directory
 */
function remove_dir($dirname)
{
    if (!file_exists(($dirname))) {
        return;
    }
    if (!is_dir($dirname)) {
        error("$dirname is not a directory");
    }
    info("Removing $dirname");
    shell_exec("rm -rf $dirname");
}

/**
 * Validates the users system is ready to run the script
 */
function validate_system()
{
    $token = github_token();
    if (!$token || !is_string($token)) {
        error('Could not get github token - set MS_GITHUB_TOKEN environment variable');
    }
    if (!cmd('which git', '.')) {
        error('git is not installed');
    }
}

/**
 * Reads MS_GITHUB_TOKEN environment variable
 */
function github_token()
{
    return getenv('MS_GITHUB_TOKEN') ?: '';
}

/**
 * Makes a request to the github API
 */
function github_api($url, $data = [])
{
    $token = github_token();
    $jsonStr = empty($data) ? '' : json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, !empty($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: silverstripe-module-standardiser',
        'Accept: application/vnd.github+json',
        "Authorization: Bearer $token",
        'X-GitHub-Api-Version: 2022-11-28'
    ]);
    if ($jsonStr) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
    }
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpcode >= 300) {
        warning("HTTP code $httpcode returned from GitHub API");
        warning($response);
        error("Failure calling github api: $url");
    }
    return json_decode($response, true);
}


function running_unit_tests()
{
    // $PRS_CREATED won't be set when running unit tests
    global $PRS_CREATED;
    return !isset($PRS_CREATED);
}

/**
 * Outputs a list of PRs created
 * Prefixed with a dash so that it's easy to copy and paste into a parent github issue
 */
function output_prs_created()
{
    if (running_unit_tests()) {
        return;
    }
    global $PRS_CREATED;
    $io = io();
    $io->writeln('');
    $io->writeln('Pull requests created:');
    foreach ($PRS_CREATED as $pr) {
        $io->writeln("- $pr");
    }
    $io->writeln('');
}

/**
 * Ouputs a list of repos that that had PRs created
 * This is intended to be used when there was an error with a run (probably a secondary rate limit) and then
 * copy pasted into the --exclude option for the next run
 */
function output_repos_with_prs_created()
{
    if (running_unit_tests()) {
        return;
    }
    global $REPOS_WITH_PRS_CREATED;
    $io = io();
    $io->writeln('');
    $io->writeln('Repos with pull requests created (add to --exclude if you need to re-run):');
    $io->writeln(implode(',', $REPOS_WITH_PRS_CREATED));
    $io->writeln('');
}

/**
 * Works out which branch in a module to checkout before running scripts on it
 * 
 * Assumes that for each module there is only a single major version per cms-major version
 */
function branch_to_checkout($branches, $currentBranch, $currentBranchCmsMajor, $cmsMajor, $branchOption)
{
    $offset = (int) $cmsMajor - (int) $currentBranchCmsMajor;
    $majorTarget = (int) $currentBranch + $offset;
    $branches = array_filter($branches, fn($branch) => preg_match('#^[0-9\.]+$#', $branch));
    usort($branches, 'version_compare');
    $branches = array_reverse($branches);
    switch ($branchOption) {
        case 'next-patch':
            $branchToCheckout = array_values(array_filter(
                $branches,
                fn($branch) => preg_match("#^$majorTarget.[0-9]+$#", $branch)
            ))[0] ?? null;
            break;
        case 'next-minor':
        default:
            $branchToCheckout = $majorTarget;
    }
    return (string) $branchToCheckout;
}

function current_branch_cms_major(
    // this param is only used for unit testing
    string $composerJson = ''
) {
    // read __composer.json of the current branch
    $contents = $composerJson ?: read_file('composer.json');

    $json = json_decode($contents);
    if (is_null($json)) {
        $lastError = json_last_error();
        error("Could not parse from composer.json - last error was $lastError");
    }
    $matchedOnBranchThreeLess = false;
    $version = preg_replace('#[^0-9\.]#', '', $json->require->{'silverstripe/framework'} ?? '');
    if (!$version) {
        $version = preg_replace('#[^0-9\.]#', '', $json->require->{'silverstripe/cms'} ?? '');
    }
    if (!$version) {
        $version = preg_replace('#[^0-9\.]#', '', $json->require->{'silverstripe/mfa'} ?? '');
    }
    if (!$version) {
        $version = preg_replace('#[^0-9\.]#', '', $json->require->{'silverstripe/assets'} ?? '');
        $matchedOnBranchThreeLess = true;
    }
    $cmsMajor = '';
    if (preg_match('#^([0-9]+)+\.?[0-9]*$#', $version, $matches)) {
        $cmsMajor = $matches[1];
        if ($matchedOnBranchThreeLess) {
            $cmsMajor += 3;
        }
    } else {
        $phpVersion = $json->require->{'php'} ?? '';
        if (substr($phpVersion,0, 4) === '^7.4') {
            $cmsMajor = 4;
        } elseif (substr($phpVersion,0, 4) === '^8.1') {
            $cmsMajor = 5;
        }
    }
    if ($cmsMajor === '') {
        error('Could not work out what the current CMS major version is');
    }
    return (string) $cmsMajor;
}