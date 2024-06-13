<?php

if (check_file_exists('README.md')) {
    $contents = read_file('README.md');
    $rx = "#(^|\n)\[!\[Silverstripe supported module\].+?\n#si";
    $contents = ltrim(preg_replace($rx, "\n", $contents), "\n");
    write_file_even_if_exists('README.md', $contents);
}
