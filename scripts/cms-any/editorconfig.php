<?php

$contents = <<<EOT
# For more information about the properties used in
# this file, please see the EditorConfig documentation:
# http://editorconfig.org/

[*]
charset = utf-8
end_of_line = lf
indent_size = 4
indent_style = space
insert_final_newline = true
trim_trailing_whitespace = true

[*.md]
trim_trailing_whitespace = false

[*.{yml,js,json,css,scss,eslintrc,feature}]
indent_size = 2
indent_style = space

[composer.json]
indent_size = 4

# Don't perform any clean-up on thirdparty files

[thirdparty/**]
trim_trailing_whitespace = false
insert_final_newline = false

[admin/thirdparty/**]
trim_trailing_whitespace = false
insert_final_newline = false
EOT;

if (is_module()) {
    write_file_if_not_exist('.editorconfig', $contents);
}
