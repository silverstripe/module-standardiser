<?php
$account = module_account();
$accountDisplay = $account === 'silverstripe' ? 'silverstripe' : "$account or silverstripe";
$conditional = schedulable_workflow_conditional($account);

// run at a random hour of the day
$runOnHour = predictable_random_int('update-js', 23);
// run at a random minute of the hour rounded to 5 minutes
$runOnMinute = predictable_random_int('update-js', 11) * 5;
// run on a 1st of the month
$runOnDay = 1;

// Runs every 6 months, one month before a scheduled minor release
$cron = "$runOnMinute $runOnHour $runOnDay 3,9 *";
$humanCron = human_cron($cron);

$content = <<<EOT
name: Update JS

on:
  workflow_dispatch:
  # $humanCron
  schedule:
    - cron: '$cron'

permissions: {}

jobs:
  update-js:
    name: Update JS
    # Only run cron on the $accountDisplay account
    if: $conditional
    runs-on: ubuntu-latest
    permissions:
      contents: write
      pull-requests: write
      actions: write
    steps:
      - name: Update JS
        uses: silverstripe/gha-update-js@v1
EOT;

if (check_file_exists('package.json') && check_file_exists('yarn.lock')) {
    write_file_even_if_exists('.github/workflows/update-js.yml', $content);
}
