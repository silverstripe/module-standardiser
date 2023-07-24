<?php

include 'vendor/autoload.php';
include 'funcs_scripts.php';
include 'funcs_utils.php';
include 'update_command.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;

// consts
const CURRENT_CMS_MAJOR = '5';
const BRANCH_OPTIONS = ['next-minor', 'next-patch'];
const DEFAULT_BRANCH = 'next-minor';
const DEFAULT_ACCOUNT = 'creative-commoners';
const DATA_DIR = '_data';
const MODULES_DIR = '_modules';
const TOOL_URL = 'https://github.com/silverstripe/module-standardiser';
const PR_TITLE = 'MNT Run module-standardiser';
const PR_DESCRIPTION = 'This pull-request was created automatically by [module-standardiser](' . TOOL_URL . ')';

// global variables
$MODULE_DIR = '';
$PRS_CREATED = [];
$REPOS_WITH_PRS_CREATED = [];
$OUT = null;

$app = new Application();
$app->register('update')
    ->setDescription('The main script of module-standardiser')
    ->addOption(
        'cms-major',
        null,
        InputOption::VALUE_REQUIRED,
        'The CMS major version to use (default: '. CURRENT_CMS_MAJOR .')'
    )
    ->addOption(
        'branch',
        null,
        InputOption::VALUE_REQUIRED,
        'The branch type to use - ' . implode('|', BRANCH_OPTIONS) . ' (default: ' . DEFAULT_BRANCH . ')'
    )
    ->addOption(
        'only',
        null,
        InputOption::VALUE_REQUIRED,
        'Only include the specified modules (without account prefix) separated by commas '
        . 'e.g. silverstripe-config,silverstripe-assets'
    )
    ->addOption(
        'exclude',
        null,
        InputOption::VALUE_REQUIRED,
        'Exclude the specified modules (without account prefix) separated by commas '
        . 'e.g. silverstripe-mfa,silverstripe-totp'
    )
    ->addOption(
        'dry-run',
        null,
        InputOption::VALUE_NONE,
        'Do not push to github or create pull-requests'
    )
    ->addOption(
        'account',
        null,
        InputOption::VALUE_REQUIRED,
        'GitHub account to use for creating pull-requests (default: ' . DEFAULT_ACCOUNT . ')'
    )
    ->addOption(
        'no-delete',
        null,
        InputOption::VALUE_NONE,
        'Do not delete _data and _modules directories before running'
    )
    ->setCode($updateCommand);
$app->run();
