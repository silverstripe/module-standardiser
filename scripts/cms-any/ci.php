<?php

// ci.yml files have been historically manually added to repos rather than using module standardiser

// Update the ci.yml to include additional conditions for pull-request handling
// This is to prevent the CI from running on pull-requests from the same repo

$one = "    uses: silverstripe/gha-ci/.github/workflows/ci.yml@v1";
$two = implode("\n", [
  "    # Do not run if this is a pull-request from same repo i.e. not a fork repo" ,
  "    if: github.event_name != 'pull_request' || github.event.pull_request.head.repo.full_name != github.repository"
]);

$ciPath = '.github/workflows/ci.yml';
if (check_file_exists($ciPath)) {
  $contents = read_file($ciPath);
  if (str_contains($contents, $one) && !str_contains($contents, $two)) {
    $three = $two . "\n" . $one;
    $contents = str_replace($one, $three, $contents);
    write_file_even_if_exists($ciPath, $contents);
  }
}
