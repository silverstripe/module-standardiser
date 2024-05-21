<?php

// run on two consecutive days of the week
$dayOfWeek = predictable_random_int('dispatch-ci', 6);
$nextDayOfWeek = $dayOfWeek === 6 ? 0 : $dayOfWeek + 1;
$runsOnDaysOfWeek = sprintf('%s,%s', $dayOfWeek, $nextDayOfWeek);
// run at a random hour of the day
$runOnHour = predictable_random_int('dispatch-ci', 23);
// run at a random minute of the hour rounded to 5 minutes
$runOnMinute = predictable_random_int('dispatch-ci', 11) * 5;

$cron = "$runOnMinute $runOnHour * * $runsOnDaysOfWeek";
$humanCron = human_cron($cron);
$account = module_account();

$content = <<<EOT
name: Dispatch CI

on:
  # $humanCron
  schedule:
    - cron: '$cron'

permissions: {}

jobs:
  dispatch-ci:
    name: Dispatch CI
    # Only run cron on the $account account
    if: (github.event_name == 'schedule' && github.repository_owner == '$account') || (github.event_name != 'schedule')
    runs-on: ubuntu-latest
    permissions:
      contents: read
      actions: write
    steps:
      - name: Dispatch CI
        uses: silverstripe/gha-dispatch-ci@v1
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
