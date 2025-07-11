<?php

use SilverStripe\SupportedModules\MetaData;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

$updateCommand = function(InputInterface $input, OutputInterface $output): int {
    // This is the code that is executed when running the 'update' command

    // variables
    global $MODULE_DIR, $GITHUB_REF, $OUT, $PRS_CREATED, $REPOS_WITH_PRS_CREATED, $REPOS_WITH_PRS_TO_CLOSE, $CMS_MAJOR;
    $OUT = $output;

    $reposMissingBranch = [];

    // validate system is ready
    validate_system();

    // setup directories
    setup_directories($input);

    // unsupported-default-branch  option must not be used with cms-major option
    if ($input->getOption('unsupported-default-branch') && $input->getOption('cms-major')) {
        error('The --unsupported-default-branch option must not be used with the --cms-major option');
    }

    // unsupported-default-branch automatically sets branch option to github-default
    if ($input->getOption('unsupported-default-branch')) {
        $input->setOption('branch', 'github-default');
    }
    // branch
    $branchOption = $input->getOption('branch') ?: DEFAULT_BRANCH;
    if (!in_array($branchOption, BRANCH_OPTIONS)) {
        error(sprintf('Invalid branch option - must be one of: %s', implode('|', BRANCH_OPTIONS)));
    }

    // CMS major version to use
    $CMS_MAJOR = $input->getOption('cms-major') ?: MetaData::HIGHEST_STABLE_CMS_MAJOR;

    // modules
    $modules = filtered_modules($CMS_MAJOR, $input);

    // script files
    if ($input->getOption('unsupported-default-branch')) {
        $scriptFiles = script_files('unsupported');
    } elseif ($branchOption === 'github-default') {
        $scriptFiles = script_files('default-branch');
    } else {
        $scriptFiles = array_merge(
            script_files('any'),
            script_files($CMS_MAJOR),
        );
    }

    // clone repos & run scripts
    foreach ($modules as $module) {
        $account = $module['account'];
        $repo = $module['repo'];
        $cloneUrl = $module['cloneUrl'];
        $MODULE_DIR =  MODULES_DIR . "/$repo";
        $GITHUB_REF = "$account/$repo";

        // clone repo
        // always clone the actual remote even when doing update-prs even though this is slower
        // reason is because we read origin in .git/config to workout the actual $account in
        // module_account() which is very important when setting up github-action crons
        if (!file_exists($MODULE_DIR)) {
            cmd("git clone $cloneUrl", MODULES_DIR);
        }
        // set git remote
        $prAccount = $input->getOption('account') ?? DEFAULT_ACCOUNT;
        $origin = cmd('git remote get-url origin', $MODULE_DIR);
        $prOrigin = str_replace("git@github.com:$account", "git@github.com:$prAccount", $origin);
        // remove any existing pr-remote - need to do this in case we change the account option
        $remotes = explode("\n", cmd('git remote', $MODULE_DIR));
        if (in_array('pr-remote', $remotes)) {
            cmd('git remote remove pr-remote', $MODULE_DIR);
        }
        cmd("git remote add pr-remote $prOrigin", $MODULE_DIR);

        $useDefaultBranch = (has_wildcard_major_version_mapping() && !current_branch_name_is_numeric_style()) || $branchOption === 'github-default';

        if ($input->getOption('update-prs')) {
            // checkout latest existing pr branch
            cmd('git fetch pr-remote', $MODULE_DIR);
            $allBranches = explode("\n", cmd('git branch -r', $MODULE_DIR));
            // example branch name: pulls/5/module-standardiser-1691550112
            $allBranches = array_map('trim', $allBranches);
            $allBranches = array_filter($allBranches, function($branch) {
                return preg_match('#^pr\-remote/pulls/.+?/module\-standardiser\-[0-9]{10}$#', $branch);
            });
            if (empty($allBranches)) {
                warning("Could not find an existing PR branch for $repo - skipping");
                continue;
            }
            // sort so that the branch with the highest timestamp goes to position 0 in the array
            usort($allBranches, function($a, $b) {
                return (substr($a, -10) <=> substr($b, -10)) * -1;
            });
            $branchToCheckout = $allBranches[0];
            $branchToCheckout = preg_replace('#^pr\-remote/#', '', $branchToCheckout);
            $prBranch = $branchToCheckout;
            $allPRs = github_api("https://api.github.com/repos/$GITHUB_REF/pulls?per_page=100");
            $allPRs = array_filter($allPRs, function($pr) use($prBranch) {
                 return $pr['title'] === PR_TITLE && $pr['head']['ref'] === $prBranch && $pr['state'] === 'open';
            });
            if (count($allPRs) < 1) {
                warning("Could not find an existing open PR for $repo for branch $prBranch - skipping");
                continue;
            }
        } else {
            // get all branches
            $allBranches = explode("\n", cmd('git branch -r', $MODULE_DIR));
            $allBranches = array_filter($allBranches, fn($branch) => !str_contains($branch, 'HEAD ->'));
            $allBranches = array_map(fn($branch) => trim(str_replace('origin/', '', $branch)), $allBranches);
            // reset index
            $allBranches = array_values($allBranches);

            // reset to the default branch so that we can then calculate the correct branch to checkout
            // this is needed for scenarios where we may be on something unparsable like pulls/5/lorem-ipsum
            $cmd = "git symbolic-ref refs/remotes/origin/HEAD | sed 's@^refs/remotes/origin/@@'";
            $defaultBranch = cmd($cmd, $MODULE_DIR);
            cmd("git checkout $defaultBranch", $MODULE_DIR);

            $currentBranch = cmd('git rev-parse --abbrev-ref HEAD', $MODULE_DIR);

            // checkout the branch to run scripts over
            if ($useDefaultBranch) {
                $branchToCheckout = $currentBranch;
            } else {
                // ensure that we're on a standard next-minor style branch
                if (!ctype_digit($currentBranch)) {
                    $tmp = array_filter($allBranches, fn($branch) => ctype_digit($branch));
                    if (empty($tmp)) {
                        error('Could not find a next-minor style branch');
                    }
                    $currentBranch = max($tmp);
                    cmd("git checkout $currentBranch", $MODULE_DIR);
                }
                $currentBranchCmsMajor = current_branch_cms_major();
                $branchToCheckout = branch_to_checkout(
                    $allBranches,
                    $defaultBranch,
                    $currentBranch,
                    $currentBranchCmsMajor,
                    $CMS_MAJOR,
                    $branchOption
                );
            }
            // If we can't identify an appropriate branch, add to a list so we can report about it later.
            if (!in_array($branchToCheckout, $allBranches)) {
                $reposMissingBranch[] = $repo;
                continue;
            }
        }
        cmd("git checkout $branchToCheckout", $MODULE_DIR);

        // ensure that this branch actually supports the cmsMajor we're targetting
        if (!$useDefaultBranch && $branchOption !== 'github-default' && current_branch_cms_major() !== $CMS_MAJOR) {
            error("Branch $branchToCheckout does not support CMS major version $CMS_MAJOR");
        }

        if ($input->getOption('update-prs')) {
            // Delete the last commit so we're starting as through we didn't do the previous run
            $lastCommitMessage = cmd('git log -1 --pretty=%B', $MODULE_DIR);
            if ($lastCommitMessage !== PR_TITLE) {
                error("Last commit message \"$lastCommitMessage\" does not match PR_TITLE \"" . PR_TITLE . "\"");
            }
            cmd("git reset HEAD~ --hard", $MODULE_DIR);
        } else {
            // create a new branch used for the pull-request
            $timestamp = time();
            $prBranch = "pulls/$branchToCheckout/module-standardiser-$timestamp";
            cmd("git checkout -b $prBranch", $MODULE_DIR);
        }

        // run scripts
        $onlyThisScript = $input->getOption('script');
        foreach ($scriptFiles as $scriptFile) {
            if ($onlyThisScript && basename($scriptFile, '.php') !== $onlyThisScript) {
                continue;
            }
            $contents = file_get_contents($scriptFile);
            $contents = str_replace('<?php', '', $contents);
            // wrap in an anonymous function to ensure that script variables do not go into the global scope
            $contents = implode("\n", ['(function() {', $contents, '})();']);
            eval($contents);
        }

        // commit changes, push changes and create pull-request
        $status = cmd('git status', $MODULE_DIR);
        if (strpos($status, 'nothing to commit') !== false) {
            info("No changes to commit for $repo");
            if ($input->getOption('update-prs')) {
                $REPOS_WITH_PRS_TO_CLOSE[] = $GITHUB_REF;
            }
            continue;
        }
        // create new commit
        cmd('git add .', $MODULE_DIR);
        cmd("git commit -m '" . PR_TITLE . "'", $MODULE_DIR);
        if ($input->getOption('dry-run')) {
            info('Not pushing changes or creating pull-request because --dry-run option is set');
            continue;
        }
        // push changes to pr-remote
        // force pushing for cases when doing update-prs
        // double make check we're on a branch that we are willing to force push
        $currentBranch = cmd('git rev-parse --abbrev-ref HEAD', $MODULE_DIR);
        if (!preg_match('#^pulls/([0-9\.]+|master|main)/module\-standardiser\-[0-9]{10}$#', $currentBranch)) {
            error("Branch $currentBranch is not a pull-request branch");
        }
        cmd("git push -f -u pr-remote $prBranch", $MODULE_DIR);
        // create pull-request using github api
        if (!$input->getOption('update-prs')) {
            // https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28#create-a-pull-request
            $responseJson = github_api("https://api.github.com/repos/$GITHUB_REF/pulls", [
                'title' => PR_TITLE,
                'body' => PR_DESCRIPTION,
                'head' => "$prAccount:$prBranch",
                'base' => $branchToCheckout,
            ]);
            $PRS_CREATED[] = $responseJson['html_url'];
            info("Created pull-request for $repo");
        }
        $REPOS_WITH_PRS_CREATED[] = $repo;
    }
    output_repos_with_prs_created();
    output_prs_created();
    output_repos_with_prs_to_close();

    // Report about any repos for which we couldn't find the right branch.
    if (count($reposMissingBranch)) {
        $reposString = implode("\n- ", $reposMissingBranch);
        warning("Could not find branch to checkout for the following repos using --branch=$branchOption:\n- $reposString");
        return Command::FAILURE;
    }

    return Command::SUCCESS;
};
