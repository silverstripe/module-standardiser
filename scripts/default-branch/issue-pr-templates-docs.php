<?php

// The template used for pull requests
$pullRequestTemplate = <<<EOT
<!--
  Thanks for contributing, you're awesome! ⭐

  Please read https://docs.silverstripe.org/en/contributing/documentation/ if you haven't contributed to this project recently.
-->
## Description
<!--
  Please describe the changes you're making, and the reason for making them.
  For visual fixes, please include tested browsers and screenshots.
-->

## Issues
<!--
  List all issues here that this pull request fixes/resolves.
  If there is no issue already, create a new one! You must link your pull request to at least one issue.
-->
- #

## Pull request checklist
<!--
  PLEASE check each of these to ensure you have done everything you need to do!
  If there's something in this list you need help with, please ask so that we can assist you.
-->
- [ ] The target branch is correct
    - See [branches and commit messages](https://docs.silverstripe.org/en/contributing/documentation#branches-and-commit-messages)
- [ ] All commits are relevant to the purpose of the PR (e.g. no TODO comments, unrelated rewording/restructuring, or arbitrary changes)
    - Small amounts of additional changes are usually okay, but if it makes it hard to concentrate on the relevant changes, ask for the unrelated changes to be reverted, and submitted as a separate PR.
- [ ] The commit messages follow our [commit message guidelines](https://docs.silverstripe.org/en/contributing/code/#commit-messages)
- [ ] The PR follows our [contribution guidelines](https://docs.silverstripe.org/en/contributing/documentation/)
- [ ] The changes follow our [writing style guide](https://docs.silverstripe.org/en/contributing/documentation/#writing-style)
- [ ] Code examples follow our [coding conventions](https://docs.silverstripe.org/en/contributing/coding_conventions/)
- [ ] CI is green
EOT;

// The template used when clicking "Open a blank issue"
$defaultIssueTemplate = "<!-- Blank templates are for use by maintainers only! If you aren't a maintainer, please go back and pick one of the issue templates. -->";

// Defines configuration for form-style issue templates
$config = <<<EOT
blank_issues_enabled: true
contact_links:
  - name: Security Vulnerability
    url: https://docs.silverstripe.org/en/contributing/issues_and_bugs/#reporting-security-issues
    about: ⚠️ We do not use GitHub issues to track security vulnerability reports. Click "open" on the right to see how to report security vulnerabilities.
  - name: Support Question
    url: https://www.silverstripe.org/community/
    about: We use GitHub issues only to discuss bugs and new features. For support questions, please use one of the support options available in our community channels.
EOT;

// The template used for documentation issues
$documentationIssueTemplate = <<<EOT
# This will be in the developer-docs and user help repos only
name: 📖 Documentation issue
description: Report an issue regarding the documentation content
body:
  - type: markdown
    attributes:
      value: |
        We strongly encourage you to [submit a pull request](https://docs.silverstripe.org/en/contributing/documentation/) which resolves the issue.
        Issues which are accompanied with a pull request are a lot more likely to be resolved quickly.
  - type: textarea
    id: pages-affected
    attributes:
      label: Pages affected
      description: A list of links of pages which are affected by this issue
      placeholder: |

        - [Getting Started](https://docs.silverstripe.org/en/5/getting_started/)
    validations:
      required: true
  - type: textarea
    id: description
    attributes:
      label: Description
      description: A clear and concise description of the problem you've found in the documentation
    validations:
      required: true
  - type: checkboxes
    id: validations
    attributes:
      label: Validations
      description: "Before submitting the issue, please make sure you do the following:"
      options:
        - label: Check that there isn't already an issue that reports the same problem
          required: true
EOT;

$files = [
    '.github/PULL_REQUEST_TEMPLATE.md' => $pullRequestTemplate,
    '.github/ISSUE_TEMPLATE.md' => $defaultIssueTemplate,
    '.github/ISSUE_TEMPLATE/config.yml' => $config,
    '.github/ISSUE_TEMPLATE/1_docs_issue.yml' => $documentationIssueTemplate,
];

// See issue-pr-templates.php for the non-docs equivalent
if (!is_docs()) {
    return;
}

foreach ($files as $path => $contents) {
    write_file_even_if_exists($path, $contents);
}
