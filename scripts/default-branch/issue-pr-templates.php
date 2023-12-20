<?php

// The template used for pull requests
$pullRequestTemplate = <<<EOT
<!--
  Thanks for contributing, you're awesome! ⭐

  Please read https://docs.silverstripe.org/en/contributing/code/ if you haven't contributed to this project recently.
-->
## Description
<!--
  Please describe expected and observed behaviour, and what you're fixing.
  For visual fixes, please include tested browsers and screenshots.
-->

## Manual testing steps
<!--
  Include any manual testing steps here which a reviewer can perform to validate your pull request works correctly.
  Note that this DOES NOT replace unit or end-to-end tests.
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
    - See [picking the right version](https://docs.silverstripe.org/en/contributing/code/#picking-the-right-version)
- [ ] All commits are relevant to the purpose of the PR (e.g. no debug statements, unrelated refactoring, or arbitrary linting)
    - Small amounts of additional linting are usually okay, but if it makes it hard to concentrate on the relevant changes, ask for the unrelated changes to be reverted, and submitted as a separate PR.
- [ ] The commit messages follow our [commit message guidelines](https://docs.silverstripe.org/en/contributing/code/#commit-messages)
- [ ] The PR follows our [contribution guidelines](https://docs.silverstripe.org/en/contributing/code/)
- [ ] Code changes follow our [coding conventions](https://docs.silverstripe.org/en/contributing/coding_conventions/)
- [ ] This change is covered with tests (or tests aren't necessary for this change)
- [ ] Any relevant User Help/Developer documentation is updated; for impactful changes, information is added to the changelog for the intended release
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

// The template used for bug report issues
$bugReportTemplate = <<<EOT
name: 🪳 Bug Report
description: Tell us if something isn't working the way it's supposed to

body:
  - type: markdown
    attributes:
      value: |
        We strongly encourage you to [submit a pull request](https://docs.silverstripe.org/en/contributing/code/) which fixes the issue.
        Bug reports which are accompanied with a pull request are a lot more likely to be resolved quickly.
  - type: input
    id: affected-versions
    attributes:
      label: Module version(s) affected
      description: |
        What version of _this module_ have you reproduced this bug on?
        Run `composer info` to see the specific version of each module installed in your project.
        If you don't have access to that, check inside the help menu in the bottom left of the CMS.
      placeholder: x.y.z
    validations:
      required: true
  - type: textarea
    id: description
    attributes:
      label: Description
      description: A clear and concise description of the problem
    validations:
      required: true
  - type: textarea
    id: how-to-reproduce
    attributes:
      label: How to reproduce
      description: |
        ⚠️ This is the most important part of the report ⚠️
        Without a way to easily reproduce your issue, there is little chance we will be able to help you and work on a fix.
        - Please, take the time to show us some code and/or configuration that is needed for others to reproduce the problem easily.
        - If the bug is too complex to reproduce with some short code samples, please reproduce it in a public repository and provide a link to the repository along with steps for setting up and reproducing the bug using that repository.
        - If part of the bug includes an error or exception, please provide a full stack trace.
        - If any user interaction is required to reproduce the bug, please add an ordered list of steps that are required to reproduce it.
        - Be as clear as you can, but don't miss any steps out. Simply saying "create a page" is less useful than guiding us through the steps you're taking to create a page, for example.
      placeholder: |

        #### Code sample
        ```php

        ```

        #### Reproduction steps
        1.
    validations:
      required: true
  - type: textarea
    id: possible-solution
    attributes:
      label: Possible Solution
      description: |
        *Optional: only if you have suggestions on a fix/reason for the bug*
        Please consider [submitting a pull request](https://docs.silverstripe.org/en/contributing/code/) with your solution! It helps get faster feedback and greatly increases the chance of the bug being fixed.
  - type: textarea
    id: additional-context
    attributes:
      label: Additional Context
      description: "*Optional: any other context about the problem: log messages, screenshots, etc.*"
  - type: checkboxes
    id: validations
    attributes:
      label: Validations
      description: "Before submitting the issue, please make sure you do the following:"
      options:
        - label: Check that there isn't already an issue that reports the same bug
          required: true
        - label: Double check that your reproduction steps work in a fresh installation of [`silverstripe/installer`](https://github.com/silverstripe/silverstripe-installer) (with any code examples you've provided)
          required: true
EOT;

// The template used for feature request issues
$featureRequestTemplate = <<<EOT
name: 🚀 Feature Request
description: Submit a feature request (but only if you're planning on implementing it)
body:
  - type: markdown
    attributes:
      value: |
        Please only submit feature requests if you plan on implementing the feature yourself.
        See the [contributing code documentation](https://docs.silverstripe.org/en/contributing/code/#make-or-find-a-github-issue) for more guidelines about submitting feature requests.
  - type: textarea
    id: description
    attributes:
      label: Description
      description: A clear and concise description of the new feature, and why it belongs in core
    validations:
      required: true
  - type: textarea
    id: more-info
    attributes:
      label: Additional context or points of discussion
      description: |
        *Optional: Any additional context, points of discussion, etc that might help validate and refine your idea*
  - type: checkboxes
    id: validations
    attributes:
      label: Validations
      description: "Before submitting the issue, please confirm the following:"
      options:
        - label: You intend to implement the feature yourself
          required: true
        - label: You have read the [contributing guide](https://docs.silverstripe.org/en/contributing/code/)
          required: true
        - label: You strongly believe this feature should be in core, rather than being its own community module
          required: true
        - label: You have checked for existing issues or pull requests related to this feature (and didn't find any)
          required: true
EOT;

$files = [
    '.github/PULL_REQUEST_TEMPLATE.md' => $pullRequestTemplate,
    '.github/ISSUE_TEMPLATE.md' => $defaultIssueTemplate,
    '.github/ISSUE_TEMPLATE/config.yml' => $config,
    '.github/ISSUE_TEMPLATE/1_bug_report.yml' => $bugReportTemplate,
    '.github/ISSUE_TEMPLATE/2_feature_request.yml' => $featureRequestTemplate,
];

// See issue-pr-templates-docs.php for the docs equivalent
if (is_docs()) {
    return;
}

foreach ($files as $path => $contents) {
    write_file_even_if_exists($path, $contents);
}
