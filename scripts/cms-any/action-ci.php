<?php

$ci = <<<EOT
name: CI

on:
  push:
  pull_request:
  workflow_dispatch:

jobs:
  ci:
    name: CI
    uses: silverstripe/gha-action-ci/.github/workflows/action-ci.yml@v1
EOT;

if (is_gha_repository()) {
    write_file_even_if_exists('.github/workflows/action-ci.yml', $ci);
}
