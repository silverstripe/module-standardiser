<?php

if (check_file_exists('composer.json')) {
    $contents = read_file('composer.json');
    $json = json_decode($contents, true);
    if (!$json) {
        warning('Failed to parse json in composer.json');
    } else {
        $version = $json['require-dev']['silverstripe/documentation-lint'] ?? null;
        if ($version === '^1') {
            $json['require-dev']['silverstripe/documentation-lint'] = '^2';
            $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
            write_file_even_if_exists('composer.json', json_encode($json, $flags));
        }
    }
}
