<?php
$account = module_account();
$accountDisplay = $account === 'silverstripe' ? 'silverstripe' : "$account or silverstripe";
$conditional = schedulable_workflow_conditional($account);

// run at a random hour of the day
$runOnHour = predictable_random_int('update-js', 23);
// run at a random minute of the hour rounded to 5 minutes
$runOnMinute = predictable_random_int('update-js', 11) * 5;
// run on the 1st of the month
$runOnDay = 1;

// Runs every 6 months, one month before a scheduled beta minor release
$cron = "$runOnMinute $runOnHour $runOnDay 2,8 *";
$humanCron = human_cron($cron);

$content = <<<EOT
name: Update JS

on:
  workflow_dispatch:
    inputs:
      branch_type:
        description: 'The branch type to run action on'
        required: true
        default: 'schedule'
        type: choice
        options:
          - 'schedule'
          - 'prev-major-curr-minor'
          - 'curr-major-curr-minor'
          - 'curr-major-next-minor'
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
        with:
          branch_type: \${{ github.event_name == 'schedule' && 'schedule' || github.event.inputs.branch_type }}
EOT;

if (check_file_exists('package.json') && check_file_exists('yarn.lock')) {
    write_file_even_if_exists('.github/workflows/update-js.yml', $content);
}
