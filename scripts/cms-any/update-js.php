<?php
$account = module_account();

// run at a random hour of the day
$runOnHour = predictable_random_int(23);
// run at a random minute of the hour rounded to 5 minutes
$runOnMinute = predictable_random_int(11) * 5;
// run on a 1st of the month
$runOnDay = 1;

$content = <<<EOT
name: Update JS

on:
  workflow_dispatch:
  # Run on a schedule of once per quarter
  schedule:
    - cron: '$runOnMinute $runOnHour $runOnDay */3 *'

permissions: {}

jobs:
  update-js:
    name: Update JS
    # Only run cron on the $account account
    if: (github.event_name == 'schedule' && github.repository_owner == '$account') || (github.event_name != 'schedule')
    runs-on: ubuntu-latest
    permissions:
      contents: write
      pull-request: write
      actions: write
    steps:
      - name: Update JS
        uses: silverstripe/gha-update-js@v1
EOT;

if (check_file_exists('package.json')) {
    write_file_even_if_exists('.github/workflows/update-js.yml', $content);
}
