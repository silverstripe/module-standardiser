<?php

$contents = <<<EOT
name: Auto-tag
on:
  push:
    tags:
      - '*.*.*'
  workflow_dispatch:

permissions: {}

jobs:
  auto-tag:
    name: Auto-tag
    runs-on: ubuntu-latest
    permissions:
      contents: write
    steps:
      - name: Auto-tag
        uses: silverstripe/gha-auto-tag@v1
EOT;

if (is_gha_repository()) {
    write_file_even_if_exists(".github/workflows/auto-tag.yml", $contents);
}
