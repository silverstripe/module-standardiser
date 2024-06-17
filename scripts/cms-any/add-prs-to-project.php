<?php

$content = <<<EOT
name: Add new pull requests to a github project

on:
  pull_request:
    types:
      - opened
      - ready_for_review

permissions: {}

jobs:
  addprtoproject:
    # Only run on the silverstripe account
    if: github.repository_owner == 'silverstripe'
    runs-on: ubuntu-latest
    steps:
      - name: Add PR to github project
        uses: silverstripe/gha-add-pr-to-project@v1
EOT;

$actionPath = '.github/workflows/add-prs-to-project.yml';
$shouldHaveAction = module_account() === 'silverstripe' && is_module() && !module_is_recipe();

if ($shouldHaveAction) {
    write_file_even_if_exists($actionPath, $content);
} else {
    delete_file_if_exists($actionPath);
}
