<?php

// run on two consecutive days of the week
$dayOfWeek = predictable_random_int(6);
$nextDayOfWeek = $dayOfWeek === 6 ? 0 : $dayOfWeek + 1;
$runsOnDaysOfWeek = sprintf('%s,%s', $dayOfWeek, $nextDayOfWeek);
// run at a random hour of the day
$runOnHour = predictable_random_int(23);
// run at a random minute of the hour rounded to 5 minutes
$runOnMinute = predictable_random_int(11) * 5;

$cron = "$runOnMinute $runOnHour * * $runsOnDaysOfWeek";
$humanCron = human_cron($cron);
$account = module_account();

$content = <<<EOT
name: Dispatch CI

on:
  # $humanCron
  schedule:
    - cron: '$cron'

jobs:
  dispatch-ci:
    name: Dispatch CI
    # Only run cron on the $account account
    if: (github.event_name == 'schedule' && github.repository_owner == '$account') || (github.event_name != 'schedule')
    runs-on: ubuntu-latest
    steps:
      - name: Dispatch CI
        uses: silverstripe/gha-dispatch-ci@v1
EOT;

if (check_file_exists('.github/workflows/ci.yml')) {
    write_file_even_if_exists('.github/workflows/dispatch-ci.yml', $content);
}
