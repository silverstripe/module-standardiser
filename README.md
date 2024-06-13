# Module standardiser

This tools standardises some files in Silverstripe modules that's intended to run on a developers laptop and create 
a number of pull-requests in GitHub.

**This tool is only intended for use by Silverstripe core committers or the Silverstripe Ltd CMS Squad**

It will run across all modules in [supported-modules](https://github.com/silverstripe/supported-modules) list and the 
relevant branch e.g. `5` will be used depending on the command-line `--branch` option that's passed in.

It will run all scripts in the `scripts/any` folder and then run all scripts in the applicable 
`scripts/<cms-version>` folder depending on the command-line `--branch` option that's passed in.

This tool can also be used to standardise GitHub labels on all supported repositories.

## GitHub Token

This tool creates pull-request via the GitHub API. You need to set the `MS_GITHUB_TOKEN` environment variable in order 
for this to work.

Create a new GitHub token in [https://github.com/settings/tokens/new](https://github.com/settings/tokens/new) 
and only tick the `public_repo` checkbox and set it to expire in 7 days. If you do not set the correct permissions
then you will get a 404 error when attempting to create pull-requests.

Delete this token once you have finished.

## Installation

```bash
git clone git@github.com:silverstripe/module-standardiser.git
cd module-standardiser
composer install
```

## Usage - Standardising module files

```bash
MS_GITHUB_TOKEN=<token> php run.php update <options>
```

**Example usage:**
```bash
MS_GITHUB_TOKEN=abc123 php run.php update --cms-major=5 --branch=next-minor --dry-run --only=silverstripe-config,silverstripe-assets
```

### Command line options:

| Flag | Description |
| ---- | ------------|
| --cms-major=[version] | The major version of CMS to use (default: 5) which determines the list of supported modules to use |
| --branch=[type] | The branch type to use - `next-minor`\|`next-patch`\|`github-default` (default: `next-patch`) |
| --only=[modules] | Only include the specified modules (without account prefix) separated by commas e.g. `silverstripe-config,silverstripe-assets` |
| --exclude=[modules] | Exclude the specified modules (without account prefix) separated by commas e.g. `silverstripe-mfa,silverstripe-totp` |
| --dry-run | Do not push to github or create pull-requests |
| --account | GitHub account to use for creating pull-requests (default: creative-commoners) |
| --no-delete | Do not delete `_data` and `_modules` directories before running |
| --unsupported-default-branch | Only update unsupported modules that were supported in the previous CMS major. Will use the GitHub default branch for that repository. Can not be used with the `--cms-major` option and will ignore the `--branch` option. |
| --update-prs | Update existing open PRs instead of creating new PRs |

**Note** that the following flags use non-standard directories for their scripts:
- Using `--branch=github-default` will only run scripts in the `scripts/default-branch` directory.
- Using `--unsupported-default-branch` will only run scripts in the `scripts/cms-unsupported` directory.

### GitHub API secondary rate limit

You may hit a secondary GitHub rate limit because this tool may create too many pull-requests in a short space of time.
To help with this the tool will always output the urls of all pull-requests updated and also the repos that were
updated so you can add them to the --exclude flag on subsequent re-runs.

### Adding new scripts

#### Where to add your script

- `scripts/cms-<version>` to run on a specific cms-major
- `scripts/cms-any` to run on any cms-major
- `scripts/default-branch` to only run on the default branch in GitHub

Scripts will be automatically picked up and run when the tool is run. Code in the script will be
passed through `eval()` on the module that is currently being processed.

Make use of functions in `funcs_scripts.php` such as `write_file_if_not_exist()` and `read_file()` to access the
correct files in the module that is currently being processed and also to ensure that console output is consistent.

Do not use functions in `funcs_utils.php` as they are not intended to be used in scripts.

Scripts will be automatically wrapped in an anoymous function so you do not need to worry about variables crossing
over into different scripts.

## Usage - Standardising GitHub labels

```bash
MS_GITHUB_TOKEN=<token> php run.php labels <options>
```

**Example usage:**
```bash
MS_GITHUB_TOKEN=abc123 php run.php labels --dry-run --only=silverstripe-config,silverstripe-assets
```

### Command line options:

| Flag | Description |
| ---- | ------------|
| --only=[modules] | Only include the specified modules (without account prefix) separated by commas e.g. `silverstripe-config,silverstripe-assets` |
| --exclude=[modules] | Exclude the specified modules (without account prefix) separated by commas e.g. `silverstripe-mfa,silverstripe-totp` |
| --dry-run | Do not update labels in GitHub, output to terminal only |
| --no-delete | Do not delete `_data` directory before running |

## Usage - Standardising GitHub repository branch and tag rulesets

```bash
MS_GITHUB_TOKEN=<token> php run.php rulesets <options>
```

**Example usage:**
```bash
MS_GITHUB_TOKEN=abc123 php run.php rulesets --dry-run --only=silverstripe-config,silverstripe-assets
```

### Command line options:

| Flag | Description |
| ---- | ------------|
| --only=[modules] | Only include the specified modules (without account prefix) separated by commas e.g. `silverstripe-config,silverstripe-assets` |
| --exclude=[modules] | Exclude the specified modules (without account prefix) separated by commas e.g. `silverstripe-mfa,silverstripe-totp` |
| --dry-run | Do not update rulesets in GitHub, output to terminal only |
