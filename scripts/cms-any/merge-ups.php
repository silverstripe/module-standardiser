<?php

global $CMS_MAJOR;

// run on a random day of the week
$runOnDay = predictable_random_int('merge-ups', 6);
// run at a random hour of the day
$runOnHour = predictable_random_int('merge-ups', 23);
// run at a random minute of the hour rounded to 5 minutes
$runOnMinute = predictable_random_int('merge-ups', 11) * 5;

// If there's a CI workflow, offset mergeups from the CI run by 3 days
if (check_file_exists('.github/workflows/dispatch-ci.yml')) {
    $ci = read_file('.github/workflows/dispatch-ci.yml');
    preg_match("#- cron: '(.+?) (.+?) (.+?) (.+?) (.+?)'#", $ci, $matches);
    [$_, $minute, $hour, $day, $month, $dayOfWeek] = $matches;
    if ($dayOfWeek !== '*') {
        $days = explode(',', $dayOfWeek);
        $runOnDay = ($days[count($days) - 1] + 3) % 7;
    }
    if ($hour !== '*') {
        $hours = explode(',', $hour);
        $runOnHour = $hours[0];
    }
    if ($minute !== '*') {
        $runOnMinute = $minute;
    }
}

$cron = "$runOnMinute $runOnHour * * $runOnDay";
$humanCron = human_cron($cron);
$account = module_account();
$accountDisplay = $account === 'silverstripe' ? 'silverstripe' : "$account or silverstripe";
$conditional = schedulable_workflow_conditional($account);

$version = ($CMS_MAJOR < 6) ? '1' : '2';

$content = <<<EOT
name: Merge-up

on:
  # $humanCron
  schedule:
    - cron: '$cron'
  workflow_dispatch:

permissions: {}

jobs:
  merge-up:
    name: Merge-up
    # Only run cron on the $accountDisplay account
    if: $conditional
    runs-on: ubuntu-latest
    permissions:
      contents: write
      actions: write
    steps:
      - name: Merge-up
        uses: silverstripe/gha-merge-up@v$version
EOT;

// rename any existing misnamed merge-ups.yml to merge-up.yml
if (check_file_exists('.github/workflows/merge-ups.yml')) {
    rename_file_if_exists('.github/workflows/merge-ups.yml', '.github/workflows/merge-up.yml');
}

if (current_branch_name_is_numeric_style() && !module_is_recipe()) {
    write_file_even_if_exists('.github/workflows/merge-up.yml', $content);
} else {
    // remove any merge-up.yml that was previously added though shouldn't be there
    delete_file_if_exists('.github/workflows/merge-up.yml');
}
