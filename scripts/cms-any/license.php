<?php

$year = date('Y');

$contents = <<<EOT
BSD 3-Clause License

Copyright (c) $year, Silverstripe Limited - www.silverstripe.com
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

* Redistributions of source code must retain the above copyright notice, this
  list of conditions and the following disclaimer.

* Redistributions in binary form must reproduce the above copyright notice,
  this list of conditions and the following disclaimer in the documentation
  and/or other materials provided with the distribution.

* Neither the name of the copyright holder nor the names of its
  contributors may be used to endorse or promote products derived from
  this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
EOT;

// standard filename used on framework, admin, cms, etc
$licenseFilename = 'LICENSE';

// can rename the licence filename on any account
foreach (['LICENSE.md', 'license.md', 'license'] as $filename) {
    rename_file_if_exists($filename, $licenseFilename);
}
// only update licence contents if module is on silverstripe account
if (module_account() === 'silverstripe') {
    if (check_file_exists($licenseFilename)) {
        $oldContents = read_file($licenseFilename);
        $newContents = str_replace('SilverStripe', 'Silverstripe', $oldContents);
        if ($newContents !== $oldContents) {
            write_file_even_if_exists($licenseFilename, $newContents);
        }
    } else {
        write_file_if_not_exist($licenseFilename, $contents);
    }
}
