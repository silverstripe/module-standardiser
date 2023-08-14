# Module standardiser

This tools standardises some files in Silverstripe modules that's intended to run on a developers laptop and create 
a number of pull-requests in GitHub.

**This tool is only intended for use by Silverstripe core committers or the Silverstripe Ltd CMS Squad**

It will run across all modules in [supported-modules](https://github.com/silverstripe/supported-modules) list and the 
relevant branch e.g. `5` will be used depending on the command-line `--branch` option that's passed in.

It will run all scripts in the `scripts/any` folder and then run all scripts in the applicable 
`scripts/<cms-version>` folder depending on the command-line `--branch` option that's passed in.

## GitHub Token

This tool creates pull-request via the GitHub API. You need to set the `MS_GITHUB_TOKEN` environment variable in order 
for this to work.

Create a new GitHub token in [https://github.com/settings/tokens/new](https://github.com/settings/tokens/new) 
and only tick the `public_repo` checkbox and set it to expire in 7 days. If you do not set the correct permissions
then you will get a 404 error when attempting to create pull-requests.

Delete this token once you have finished.

## Usage

```bash
git clone git@github.com:silverstripe/module-standardiser.git
cd module-standardiser
composer install
MS_GITHUB_TOKEN=<token> php run.php update <options>
```

**Example usage:**
```bash
MS_GITHUB_TOKEN=abc123 php run.php update --cms-major=5 --branch=next-minor --dry-run --only=silverstripe-config,silverstripe-assets
```

## Command line options:

| Flag | Description |
| ---- | ------------|
| --cms-major=[version] | The major version of CMS to use (default: 5) |
| --branch=[type] | The branch type to use - next-minor\|next-patch (default: next-minor) |
| --only=[modules] | Only include the specified modules (without account prefix) separated by commas e.g. silverstripe-config,silverstripe-assets |
| --exclude=[modules] | Exclude the specified modules (without account prefix) separated by commas e.g. silverstripe-mfa,silverstripe-totp |
| --dry-run | Do not push to github or create pull-requests |
| --account | GitHub account to use for creating pull-requests (default: creative-commoners) |
| --no-delete | Do not delete _data and _modules directories before running |
| --update-prs | Update existing open PRs instead of creating new PRs |

## GitHub API secondary rate limit

You may hit a secondary GitHub rate limit because this tool may create too many pull-requests in a short space of time. 
To help with this the tool will always output the urls of all pull-requests updated and also the repos that were 
updated so you can add them to the --exclude flag on subsequent re-runs.

## Adding new scripts

Simply add new scripts to either `scripts/cms-<version>` to run on a specific cms-major or `scripts/cms-any` to run 
on any cms-major and they will be automatically picked up and run when the tool is run. Code in the script will be 
passed through `eval()` on the module that is currently being processed.

Make use of functions in `funcs_scripts.php` such as `write_file_if_not_exist()` and `read_file()` to access the 
correct files in the module that is currently being processed and also to ensure that console output is consistent.

Do not use functions in `funcs_utils.php` as they are not intended to be used in scripts.

Scripts will be automatically wrapped in an anoymous function so you do not need to worry about variables crossing 
over into different scripts.

## Updating the tool when a new major version of CMS is updated

Update the `CURRENT_CMS_MAJOR` constant in `run.php`
