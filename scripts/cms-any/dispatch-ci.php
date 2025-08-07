<?php

// run on three consecutive days of the week
$dayOfWeek = predictable_random_int('dispatch-ci', 6);
$nextDayOfWeek = $dayOfWeek === 6 ? 0 : $dayOfWeek + 1;
$nextNextDayOfWeek = $dayOfWeek === 5 ? 0 : ($dayOfWeek === 6 ? 1 : $dayOfWeek + 2);
$runsOnDaysOfWeek = sprintf('%s,%s,%s', $dayOfWeek, $nextDayOfWeek, $nextNextDayOfWeek);
// run at a random hour of the day
$runOnHour = predictable_random_int('dispatch-ci', 23);
// run at a random minute of the hour rounded to 5 minutes
$runOnMinute = predictable_random_int('dispatch-ci', 11) * 5;

$cron = "$runOnMinute $runOnHour * * $runsOnDaysOfWeek";
$humanCron = human_cron($cron);
$account = module_account();
$accountDisplay = $account === 'silverstripe' ? 'silverstripe' : "$account or silverstripe";
$conditional = schedulable_workflow_conditional($account);

// workflow_dispatch inputs match the inputs on the gha-dispatch-ci action and are intended to
// assist in testing and debugging any issues. The regular cron will not supply any inputs and instead
// the action will dynamically choose which branch to run ci dynamically
// View https://github.com/silverstripe/gha-dispatch-ci for details on what each input does
$content = <<<EOT
name: Dispatch CI

on:
  # $humanCron
  schedule:
    - cron: '$cron'
  workflow_dispatch:
    inputs:
      major_type:
        description: 'Major branch type'
        required: true
        type: choice
        options:
          - 'dynamic'
          - 'current'
          - 'next'
          - 'previous'
        default: 'dynamic'
      minor_type:
        description: 'Minor branch type'
        required: true
        type: choice
        options:
          - 'dynamic'
          - 'next-minor'
          - 'next-patch'
        default: 'dynamic'

permissions: {}

jobs:
  dispatch-ci:
    name: Dispatch CI
    # Only run cron on the $accountDisplay account
    if: $conditional
    runs-on: ubuntu-latest
    permissions:
      contents: read
      actions: write
    steps:
      - name: Dispatch CI
        uses: silverstripe/gha-dispatch-ci@v1
        with:
          major_type: \${{ inputs.major_type }}
          minor_type: \${{ inputs.minor_type }}
EOT;

$dispatchCiPath = '.github/workflows/dispatch-ci.yml';
$ciPath = '.github/workflows/ci.yml';
$shouldHaveDispatchCi = (is_module() || is_composer_plugin()) && !is_docs() && !is_gha_repository();
// If module non has_wildcard_major_version_mapping then dispatch-ci.yml should always be present
if (!has_wildcard_major_version_mapping()) {
  $shouldHaveDispatchCi = true;
}

if ($shouldHaveDispatchCi) {
  if (check_file_exists($ciPath)) {
    write_file_even_if_exists($dispatchCiPath, $content);
  }
} else {
  delete_file_if_exists($dispatchCiPath);
}
