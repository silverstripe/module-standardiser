<?php

$account = module_account();

$content = <<<EOT
name: Tag patch release

on:
  # https://docs.github.com/en/actions/using-workflows/events-that-trigger-workflows#workflow_dispatch
  workflow_dispatch:
    inputs:
      latest_local_sha:
        description: The latest local sha
        required: true
        type: string

permissions: {}

jobs:
  tagpatchrelease:
    name: Tag patch release
    # Only run cron on the $account account
    if: (github.event_name == 'schedule' && github.repository_owner == '$account') || (github.event_name != 'schedule')
    runs-on: ubuntu-latest
    permissions:
      contents: write
    steps:
      - name: Tag release
        uses: silverstripe/gha-tag-release@v2
        with:
          latest_local_sha: \${{ inputs.latest_local_sha }}
EOT;

$workflowPath = '.github/workflows/tag-patch-release.yml';
$ciPaths = [ '.github/workflows/ci.yml', '.github/workflows/action-ci.yml' ];
$shouldHaveAction = false;

foreach ($ciPaths as $ciPath) {
    if (check_file_exists($ciPath)) {
        $shouldHaveAction = true;
    }
}

if ($shouldHaveAction) {
  write_file_even_if_exists($workflowPath, $content);
} else {
  delete_file_if_exists($workflowPath);
}
