<?php

$ciFilePath = '.github/workflows/ci.yml';

if (!check_file_exists($ciFilePath)) {
    return;
}

$content = read_file($ciFilePath);

if (!str_contains($content, 'uses: silverstripe/gha-ci/.github/workflows/ci.yml@v1')) {
    return;
}

$content = str_replace(
    'uses: silverstripe/gha-ci/.github/workflows/ci.yml@v1',
    'uses: silverstripe/gha-ci/.github/workflows/ci.yml@v2',
    $content
);

write_file_even_if_exists($ciFilePath, $content);
