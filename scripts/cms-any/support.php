<?php

// This file propogates from the .github repository.
// See https://docs.github.com/en/communities/setting-up-your-project-for-healthy-contributions/creating-a-default-community-health-file#supported-file-types
if (module_account() === 'silverstripe' && module_name() !== '.github') {
    delete_file_if_exists('support.md');
    delete_file_if_exists('SUPPORT.md');
    delete_file_if_exists('support');
    delete_file_if_exists('SUPPORT');
}
