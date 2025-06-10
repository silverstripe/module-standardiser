<?php

if (check_file_exists('composer.json')) {
    $contents = read_file('composer.json');
    $json = json_decode($contents, true);
    if (!$json) {
        warning("Failed to parse json in $path");
    } else {
        $version = $json['require-dev']['phpunit/phpunit'] ?? null;
        if ($version === '^9.5') {
            $json['require-dev']['phpunit/phpunit'] = '^9.6';
            $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
            write_file_even_if_exists('composer.json', json_encode($json, $flags));
        }
    }
}
