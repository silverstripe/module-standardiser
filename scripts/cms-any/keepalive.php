<?php

// run on a day of the month up to the 28th
$runOnDay = predictable_random_int('keepalive', 27, 1);
// run at a random hour of the day
$runOnHour = predictable_random_int('keepalive', 23);
// run at a random minute of the hour rounded to 5 minutes
$runOnMinute = predictable_random_int('keepalive', 11) * 5;

$cron = "$runOnMinute $runOnHour $runOnDay * *";
$humanCron = human_cron($cron);
$account = module_account();
$accountDisplay = $account === 'silverstripe' ? 'silverstripe' : "$account or silverstripe";
$conditional = schedulable_workflow_conditional($account);

$content = <<<EOT
name: Keepalive

on:
  # $humanCron
  schedule:
    - cron: '$cron'
  workflow_dispatch:

permissions: {}

jobs:
  keepalive:
    name: Keepalive
    # Only run cron on the $accountDisplay account
    if: $conditional
    runs-on: ubuntu-latest
    permissions:
      actions: write
    steps:
      - name: Keepalive
        uses: silverstripe/gha-keepalive@v1
EOT;

write_file_even_if_exists('.github/workflows/keepalive.yml', $content);
