<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

$updateCommand = function(InputInterface $input, OutputInterface $output): int {
    // This is the code that is executed when running the 'update' command

    // variables
    global $MODULE_DIR, $OUT, $PRS_CREATED, $REPOS_WITH_PRS_CREATED;
    $OUT = $output;

    // validate system is ready
    validate_system();

    // setup directories
    if (!$input->getOption('no-delete')) {
        remove_dir(DATA_DIR);
        remove_dir(MODULES_DIR);
    }
    if (!file_exists(DATA_DIR)) {
        mkdir(DATA_DIR);
    }
    if (!file_exists(MODULES_DIR)) {
        mkdir(MODULES_DIR);
    }

    // branch
    $branchOption = $input->getOption('branch') ?: DEFAULT_BRANCH;
    if (!in_array($branchOption, BRANCH_OPTIONS)) {
        error(sprintf('Invalid branch option - must be one of: %s', implode('|', BRANCH_OPTIONS)));
    }

    // CMS major version to use
    $cmsMajor = $input->getOption('cms-major') ?: CURRENT_CMS_MAJOR;

    // modules
    $modules = supported_modules($cmsMajor);
    if ($cmsMajor === CURRENT_CMS_MAJOR) {
        // only include extra_repositories() when using the current CMS major version because the extra rexpositories
        // don't have multi majors branches supported e.g. gha-generate-matrix
        $modules = array_merge($modules, extra_repositories());
    }
    if ($input->getOption('only')) {
        $only = explode(',', $input->getOption('only'));
        $modules = array_filter($modules, function ($module) use ($only) {
            return in_array($module['repo'], $only);
        });
    }
    if ($input->getOption('exclude')) {
        $exclude = explode(',', $input->getOption('exclude'));
        $modules = array_filter($modules, function ($module) use ($exclude) {
            return !in_array($module['repo'], $exclude);
        });
    }

    // script files
    if ($branchOption === 'github-default') {
        $scriptFiles = script_files('default-branch');
    } else {
        $scriptFiles = array_merge(
            script_files('any'),
            script_files($cmsMajor),
        );
    }

    // clone repos & run scripts
    foreach ($modules as $module) {
        $account = $module['account'];
        $repo = $module['repo'];
        $cloneUrl = $module['cloneUrl'];
        $MODULE_DIR =  MODULES_DIR . "/$repo";
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

        if ($input->getOption('update-prs')) {
            // checkout latest existing pr branch
            cmd('git fetch pr-remote', $MODULE_DIR);
            $allBranches = explode("\n", cmd('git branch -r', $MODULE_DIR));
            // example branch name: pulls/5/module-standardiser-1691550112
            $allBranches = array_map('trim', $allBranches);
            $allBranches = array_filter($allBranches, function($branch) {
                return preg_match('#^pr\-remote/pulls/[0-9\.]+/module\-standardiser\-[0-9]{10}$#', $branch);
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
            $allPRs = github_api("https://api.github.com/repos/$account/$repo/pulls?per_page=100");
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
            $allBranches = array_map(fn($branch) => trim(str_replace('origin/', '', $branch)), $allBranches);

            // reset to the default branch so that we can then calculate the correct branch to checkout
            // this is needed for scenarios where we may be on something unparsable like pulls/5/lorem-ipsum
            $cmd = "git symbolic-ref refs/remotes/origin/HEAD | sed 's@^refs/remotes/origin/@@'";
            $defaultBranch = cmd($cmd, $MODULE_DIR);
            cmd("git checkout $defaultBranch", $MODULE_DIR);

            // checkout the branch to run scripts over
            $currentBranch = cmd('git rev-parse --abbrev-ref HEAD', $MODULE_DIR);
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
                $cmsMajor,
                $branchOption
            );
            if (!in_array($branchToCheckout, $allBranches)) {
                error("Could not find branch to checkout for $repo using --branch=$branchOption");
            }
        }
        cmd("git checkout $branchToCheckout", $MODULE_DIR);

        // ensure that this branch actually supports the cmsMajor we're targetting
        if ($branchOption !== 'github-default' && current_branch_cms_major() !== $cmsMajor) {
            error("Branch $branchToCheckout does not support CMS major version $cmsMajor");
        }

        // create a new branch used for the pull-request
        if (!$input->getOption('update-prs')) {
            $timestamp = time();
            $prBranch = "pulls/$branchToCheckout/module-standardiser-$timestamp";
            cmd("git checkout -b $prBranch", $MODULE_DIR);
        }

        // run scripts
        foreach ($scriptFiles as $scriptFile) {
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
            continue;
        }
        cmd('git add .', $MODULE_DIR);
        if ($input->getOption('update-prs')) {
            // squash on to existing commit
            $lastCommitMessage = cmd('git log -1 --pretty=%B', $MODULE_DIR);
            if ($lastCommitMessage !== PR_TITLE) {
                error("Last commit message \"$lastCommitMessage\" does not match PR_TITLE \"" . PR_TITLE . "\"");
            }
            cmd("git commit --amend --no-edit", $MODULE_DIR);
        } else {
            // create new commit
            cmd("git commit -m '" . PR_TITLE . "'", $MODULE_DIR);
        }
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
            $responseJson = github_api("https://api.github.com/repos/$account/$repo/pulls", [
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
    return Command::SUCCESS;
};
