<?php

// Only valid for non-theme modules
if (!is_module() || is_theme()) {
    return;
}

// Get the dirs we want to run static analysis against
$dirs = [];
foreach (['code', 'src', 'app/src'] as $codeDir) {
    if (check_dir_exists($codeDir)) {
        $dirs[] = $codeDir;
    }
}

// If we have some dirs to run against, we don't need to do anything.
if (empty($dirs)) {
    return;
}

$content = <<<EOT
parameters:
  paths:
    - %s

EOT;

// Create a phpstan config file
write_file_even_if_exists('phpstan.neon.dist', sprintf($content, implode("\n    - ", $dirs)));

// Add composer dependencies.
// composer.json file is already guaranteed to exist - it's used in the is_module()/is_theme() checks above.
// Do not add allow-plugins config - we don't want to be enforcing that for peoples' projects
$contents = read_file('composer.json');
$json = json_decode($contents, true);
$jsonOrig = $json;
if (!$json) {
    warning('Failed to parse composer.json');
} else {
    if (!array_key_exists('require-dev', $json)) {
        $json['require-dev'] = [];
    }
    $json['require-dev']['silverstripe/standards'] ??= '^1';
    $json['require-dev']['phpstan/extension-installer'] ??= '^1.3';

    if ($json !== $jsonOrig) {
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        write_file_even_if_exists('composer.json', json_encode($json, $flags));
    }
}
