<?php

$account = module_account();
$accountDisplay = $account === 'silverstripe' ? 'silverstripe' : "$account or silverstripe";
$conditional = schedulable_workflow_conditional($account);

$permissions = <<<EOT
permissions:
      contents: write
EOT;
if (is_gha_repository()) {
  // gha repositories will dispatch auto-tag.yml from within the gha-tag-release action
  $permissions = <<<EOT
  permissions:
        actions: write
        contents: write
  EOT;
}

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
    # Only run cron on the $accountDisplay account
    if: $conditional
    runs-on: ubuntu-latest
    $permissions
    steps:
      - name: Tag release
        uses: silverstripe/gha-tag-release@v2
        with:
          latest_local_sha: \${{ inputs.latest_local_sha }}
EOT;

if (is_gha_repository()) {
    $content .= "\n          dispatch_gha_autotag: true\n";
}

$workflowPath = '.github/workflows/tag-patch-release.yml';
$ciPaths = [ '.github/workflows/ci.yml', '.github/workflows/action-ci.yml' ];
$shouldHaveAction = false;

foreach ($ciPaths as $ciPath) {
    if (check_file_exists($ciPath)) {
        $shouldHaveAction = true;
    }
}

$notAllowedRepos = [
    'cow',
    'rhino',
    'github-issue-search-client',
    'module-standardiser',
    'supported-modules',
];
$shouldHaveAction = $shouldHaveAction && !is_misc() && !module_is_recipe() && !module_is_one_of($notAllowedRepos);

if ($shouldHaveAction) {
  write_file_even_if_exists($workflowPath, $content);
} else {
  delete_file_if_exists($workflowPath);
}
