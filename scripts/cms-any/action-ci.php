<?php

$ci = <<<EOT
name: Action CI

on:
  push:
  pull_request:
  workflow_dispatch:

jobs:
  actionci:
    name: Action CI
    uses: silverstripe/gha-action-ci/.github/workflows/action-ci.yml@v1
EOT;

if (is_gha_repository()) {
    $filename = module_name() === 'gha-action-ci' ? 'action-ci-self.yml' : 'action-ci.yml';
    write_file_even_if_exists(".github/workflows/$filename", $ci);
}
