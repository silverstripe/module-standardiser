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
        ];
    }
    return $modules;
}

/**
 * Hardcoded list of non-supported, additional repositories to standardise (e.g. silverstripe/gha-*)
 *
 * Repositories in this list should only have a single supported major version
 * This will only be included if the $cmsMajor is the CURRENT_CMS_MAJOR
 */
function extra_repositories()
{
    $importantRepos = [
        'silverstripe/markdown-php-codesniffer',
        'silverstripe/silverstripe-standards',
    ];
    $modules = [];
    // iterating to page 10 will be enough to get all the repos well into the future
    for ($i = 0; $i < 10; $i++) {
        $path = "_data/extra_repositories-$i.json";
        if (file_exists($path)) {
            info("Reading local data from $path");
            $json = json_decode(file_get_contents($path), true);
        } else {
            $json = github_api("https://api.github.com/orgs/silverstripe/repos?per_page=100&page=$i");
            file_put_contents($path, json_encode($json, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }
        if (empty($json)) {
            break;
        }
        foreach ($json as $repo) {
            if ($repo['archived']) {
                continue;
            }
            $ghrepo = $repo['full_name'];
            // Only include repos we care about
            if (!in_array($ghrepo, $importantRepos) && strpos($ghrepo, '/gha-') === false) {
                continue;
            }
            $modules[] = [
                'ghrepo' => $ghrepo,
                'account' => explode('/', $ghrepo)[0],
                'repo' => explode('/', $ghrepo)[1],
                'cloneUrl' => "git@github.com:$ghrepo.git",
            ];
        }
    }
    return $modules;
}

/**
 * Returns a list of all scripts files to run against a particular cms major version
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
    info("Making curl request to $url");
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
 * Uses composer.json to workout the current branch cms major version
 *
 * If composer.json does not exist then it's assumed to be CURRENT_CMS_MAJOR
 */
function current_branch_cms_major(
    // this param is only used for unit testing
    string $composerJson = ''
) {
    global $MODULE_DIR;

    // Some repositories don't have a valid matching CMS major
    $ignoreCMSMajor = [
        '/silverstripe-simple',
        '/markdown-php-codesniffer',
    ];
    foreach ($ignoreCMSMajor as $ignore) {
        if (strpos($MODULE_DIR, $ignore) !== false) {
            return CURRENT_CMS_MAJOR;
        }
    }

    if ($composerJson) {
        $contents = $composerJson;
    } elseif (check_file_exists('composer.json')) {
        $contents = read_file('composer.json');
    } else {
        return CURRENT_CMS_MAJOR;
    }

    // special logic for developer-docs
    if (strpos($MODULE_DIR, '/developer-docs') !== false) {
        $currentBranch = cmd('git rev-parse --abbrev-ref HEAD', $MODULE_DIR);
        if (!preg_match('#^(pulls/)?([0-9]+)(\.[0-9]+)?(/|$)#', $currentBranch, $matches)) {
            error("Could not work out current major for developer-docs from branch $currentBranch");
        }
        return $matches[2];
    }

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
        if ($version) {
            $matchedOnBranchThreeLess = true;
        }
    }
    if (!$version) {
        $version = preg_replace('#[^0-9\.]#', '', $json->require->{'cwp/starter-theme'} ?? '');
        if ($version) {
            $version += 1;
        }
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

function filtered_modules($cmsMajor, $input) {
    $modules = supported_modules($cmsMajor);
    if ($cmsMajor === CURRENT_CMS_MAJOR) {
        // only include extra_repositories() when using the current CMS major version because the extra rexpositories
        // don't have multi majors branches supported e.g. gha-generate-matrix
        $modules = array_merge($modules, extra_repositories());
    }
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
