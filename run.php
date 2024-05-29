<?php

include 'vendor/autoload.php';
include 'funcs_scripts.php';
include 'funcs_utils.php';
include 'update_command.php';
include 'labels_command.php';
include 'rulesets_command.php';

use SilverStripe\SupportedModules\MetaData;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;

// consts
const BRANCH_OPTIONS = ['next-minor', 'next-patch', 'github-default'];
const DEFAULT_BRANCH = 'next-patch';
const DEFAULT_ACCOUNT = 'creative-commoners';
const DATA_DIR = '_data';
const MODULES_DIR = '_modules';
const TOOL_URL = 'https://github.com/silverstripe/module-standardiser';
const PR_TITLE = 'MNT Run module-standardiser';
const PR_DESCRIPTION = 'This pull-request was created automatically by [module-standardiser](' . TOOL_URL . ')';
const BRANCH_RULESET_NAME = 'Silverstripe CMS branch ruleset';
const TAG_RULESET_NAME = 'Silverstripe CMS tag ruleset';

// global variables
$MODULE_DIR = '';
$GITHUB_REF = '';
$PRS_CREATED = [];
$REPOS_WITH_PRS_CREATED = [];
$REPOS_WITH_LABELS_UPDATED = [];
$REPOS_WITH_RULESETS_UPDATED = [];
$OUT = null;

// options
$optionCmsMajor = [
    'cms-major',
    null,
    InputOption::VALUE_REQUIRED,
    'The CMS major version to use (default: '. MetaData::HIGHEST_STABLE_CMS_MAJOR .')'
];
$optionBranch = [
    'branch',
    null,
    InputOption::VALUE_REQUIRED,
    'The branch type to use - ' . implode('|', BRANCH_OPTIONS) . ' (default: ' . DEFAULT_BRANCH . ')'
];
$optionOnly = [
    'only',
    null,
    InputOption::VALUE_REQUIRED,
    'Only include the specified modules (without account prefix) separated by commas '
    . 'e.g. silverstripe-config,silverstripe-assets'
];
$optionExclude = [
    'exclude',
    null,
    InputOption::VALUE_REQUIRED,
    'Exclude the specified modules (without account prefix) separated by commas '
    . 'e.g. silverstripe-mfa,silverstripe-totp'
];
$optionDryRun = [
    'dry-run',
    null,
    InputOption::VALUE_NONE,
    'Do not push to github or create pull-requests'
];
$optionAccount = [
    'account',
    null,
    InputOption::VALUE_REQUIRED,
    'GitHub account to use for creating pull-requests (default: ' . DEFAULT_ACCOUNT . ')'
];
$optionNoDelete = [
    'no-delete',
    null,
    InputOption::VALUE_NONE,
    'Do not delete _data and _modules directories before running'
];
$optionUpdatePrs = [
    'update-prs',
    null,
    InputOption::VALUE_NONE,
    'Checkout out and update the latest open PR instead of creating a new one'
];

$app = new Application();

$app->register('update')
    ->setDescription('The main script of module-standardiser')
    ->addOption(...$optionCmsMajor)
    ->addOption(...$optionBranch)
    ->addOption(...$optionOnly)
    ->addOption(...$optionExclude)
    ->addOption(...$optionDryRun)
    ->addOption(...$optionAccount)
    ->addOption(...$optionNoDelete)
    ->addOption(...$optionUpdatePrs)
    ->setCode($updateCommand);

$app->register('labels')
    ->setDescription('Script to set labels on all repos')
    ->addOption(...$optionOnly)
    ->addOption(...$optionExclude)
    ->addOption(...$optionDryRun)
    ->addOption(...$optionNoDelete)
    ->setCode($labelsCommand);

$app->register('rulesets')
    ->setDescription('Script to set rulesets on all repos only on the silverstripe account')
    ->addOption(...$optionOnly)
    ->addOption(...$optionExclude)
    ->addOption(...$optionDryRun)
    ->setCode($rulesetsCommand);

try {
    $app->run();
} catch (Error|Exception $e) {
    // Make sure we output and information about PRs which were raised before killing the process.
    error("file: {$e->getFile()}\nline: {$e->getLine()}\nmessage: {$e->getMessage()}");
}
