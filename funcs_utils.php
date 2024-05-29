<?php

use SilverStripe\SupportedModules\BranchLogic;
use SilverStripe\SupportedModules\MetaData;
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
        mkdir($dirname, 0775, true);
    }
    $contents = trim($contents) . "\n";
    $existingContents = file_exists($path) ? file_get_contents($path) : '';
    if ($existingContents && $existingContents === $contents) {
        info("Contents of $path is already correct, continuing");
        return;
    }
    file_put_contents($path, $contents);
    info("Wrote to $path");
}

/**
 * Returns a list of all scripts files to run against a particular cms major version
 * Scripts will be alphabetically sorted
 */
function script_files($cmsMajor)
{
    if ($cmsMajor === 'default-branch') {
        $dir = 'scripts/default-branch';
    } else {
        if (!ctype_digit($cmsMajor)) {
            $cmsMajor = "-$cmsMajor";
        }
        $scriptFiles = [];
        $dir = "scripts/cms$cmsMajor";
    }
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
    sort($scriptFiles);
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
function github_api($url, $data = [], $httpMethod = '')
{
    // silverstripe-themes has a kind of weird redirect only for api requests
    $url = str_replace('/silverstripe-themes/silverstripe-simple', '/silverstripe/silverstripe-simple', $url);
    $method = $httpMethod ? strtoupper($httpMethod) : 'GET';
    info("Making $method curl request to $url");
    $token = github_token();
    $jsonStr = empty($data) ? '' : json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, !empty($data));
    if ($httpMethod) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
    }
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
 * Outputs a list of repos that that had labels updated
 * If there was an error with a run (probably a secondary rate limit), this can be
 * copy pasted into the --exclude option for the next run to continue from where you left off
 */
function output_repos_with_labels_updated()
{
    if (running_unit_tests()) {
        return;
    }
    global $REPOS_WITH_LABELS_UPDATED;
    $io = io();
    $io->writeln('');
    $io->writeln('Repos with labels created (add to --exclude if you need to re-run):');
    $io->writeln(implode(',', $REPOS_WITH_LABELS_UPDATED));
    $io->writeln('');
}

/**
 * Outputs a list of repos that that had rulesets updated
 * If there was an error with a run (probably a secondary rate limit), this can be
 * copy pasted into the --exclude option for the next run to continue from where you left off
 */
function output_repos_with_rulesets_created_or_updated()
{
    if (running_unit_tests()) {
        return;
    }
    global $REPOS_WITH_RULESETS_UPDATED;
    $io = io();
    $io->writeln('');
    $io->writeln('Repos with rulesets created/updated (add to --exclude if you need to re-run):');
    $io->writeln(implode(',', $REPOS_WITH_RULESETS_UPDATED));
    $io->writeln('');
}

function create_ruleset($type, $additionalBranchConditions = [])
{
    $ruleset = file_get_contents("rulesets/$type-ruleset.json");
    if (!$ruleset) {
        error("Could not read ruleset for $type");
    }
    $json = json_decode($ruleset, true);
    if ($type == 'branch') {
        $json['name'] = BRANCH_RULESET_NAME;
    } elseif ($type === 'tag') {
        $json['name'] = TAG_RULESET_NAME;
    } else {
        error("Invalid ruleset type: $type");
    }
    foreach ($additionalBranchConditions as $value) {
        $json['conditions']['ref_name']['include'][] = $value;
    }
    return $json;
}

/**
 * Works out which branch in a module to checkout before running scripts on it
 *
 * Assumes that for each module there is only a single major version per cms-major version
 */
function branch_to_checkout($branches, $defaultBranch, $currentBranch, $currentBranchCmsMajor, $cmsMajor, $branchOption)
{
    $offset = (int) $cmsMajor - (int) $currentBranchCmsMajor;
    $majorTarget = (int) $currentBranch + $offset;
    $branches = array_filter($branches, fn($branch) => preg_match('#^[0-9\.]+$#', $branch));
    usort($branches, 'version_compare');
    $branches = array_reverse($branches);
    switch ($branchOption) {
        case 'github-default':
            $branchToCheckout = $defaultBranch;
            break;
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

/**
 * Works out the current branch cms major version
 */
function current_branch_cms_major(
    // this param is only used for unit testing
    string $composerJson = ''
) {
    global $MODULE_DIR, $GITHUB_REF;

    // This repo matches every major and matches start at the lowest - but we only want the highest stable.
    if ($GITHUB_REF === 'silverstripe/silverstripe-simple') {
        return MetaData::HIGHEST_STABLE_CMS_MAJOR;
    }

    $contents = '';
    if ($composerJson) {
        $contents = $composerJson;
    } elseif (check_file_exists('composer.json')) {
        $contents = read_file('composer.json');
    }
    $composerJson = json_decode($contents);
    if (is_null($composerJson) && check_file_exists('composer.json')) {
        $lastError = json_last_error();
        error("Could not parse from composer.json - last error was $lastError");
    }

    $repoData = MetaData::getMetaDataForRepository($GITHUB_REF);
    $branchMajor = '';
    // If we're running unit tests, $MODULE_DIR will be some fake value with causes errors here
    if (!running_unit_tests()) {
        $currentBranch = cmd('git rev-parse --abbrev-ref HEAD', $MODULE_DIR);
        if (preg_match('#^(pulls/)?([0-9]+)(\.[0-9]+)?(/|$)#', $currentBranch, $matches)) {
            $branchMajor = $matches[2];
        }
    }
    $cmsMajor = BranchLogic::getCmsMajor($repoData, $branchMajor, $composerJson, true);

    if ($cmsMajor === '') {
        // The supported modules metadata has a bunch of repos with no specific major version mapping.
        // Just assume they're on the highest major in that case.
        return MetaData::HIGHEST_STABLE_CMS_MAJOR;
    }
    return (string) $cmsMajor;
}

function setup_directories($input, $dirs = [DATA_DIR, MODULES_DIR]) {
    if (!$input->getOption('no-delete')) {
        foreach ($dirs as $dir) {
            remove_dir($dir);
        }
    }
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir);
        }
    }
}

function filtered_modules($cmsMajor, $input)
{
    $repos = MetaData::removeReposNotInCmsMajor(
        MetaData::getAllRepositoryMetaData(false),
        $cmsMajor,
        // For repositories that only have a single support branch such as gha-generate-matrix, only include
        // them when updating the currently supported CMS major.
        $cmsMajor === MetaData::HIGHEST_STABLE_CMS_MAJOR
    );

    $modules = convert_repos_data_to_modules($repos);

    if ($input->getOption('only')) {
        $only = explode(',', $input->getOption('only'));
        $modules = array_filter($modules, function ($module) use ($only) {
            return in_array($module['repo'], $only);
        });
    }
    if ($input->getOption('exclude')) {
        $exclude = explode(',', $input->getOption('exclude'));
        $modules = array_filter($modules, function ($module) use ($exclude) {
            return !in_array($module['repo'], $exclude);
        });
    }
    return $modules;
}

function convert_repos_data_to_modules(array $repos)
{
    $modules = [];
    foreach ($repos as $repo) {
        $ghrepo = $repo['github'];
        $modules[] = [
            'ghrepo' => $ghrepo,
            'account' => explode('/', $ghrepo)[0],
            'repo' => explode('/', $ghrepo)[1],
            'cloneUrl' => "git@github.com:$ghrepo.git",
        ];
    }
    return $modules;
}
