<?php

use SilverStripe\SupportedModules\MetaData;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

$rulesetsCommand = function(InputInterface $input, OutputInterface $output): int {
    // This is the code that is executed when running the 'rulesets' command

    // Variables
    global $OUT, $REPOS_WITH_RULESETS_UPDATED;
    $OUT = $output;

    // Validate system is ready
    validate_system();

    // Modules
    $modules = [];
    $modulesCurrentMajor = filtered_modules(MetaData::HIGHEST_STABLE_CMS_MAJOR, $input);
    $modulesPreviousMajor = filtered_modules(MetaData::HIGHEST_STABLE_CMS_MAJOR - 1, $input);
    foreach ([$modulesCurrentMajor, $modulesPreviousMajor] as $modulesList) {
        foreach ($modulesList as $module) {
            // Important! Only include modules on the "silverstripe" account
            if ($module['account'] !== 'silverstripe') {
                continue;
            }
            $modules[$module['ghrepo']] = $module;
        }
    }

    // Update rulesets
    foreach ($modules as $module) {
        $account = $module['account'];
        $repo = $module['repo'];

        // Fetch existing rulesets
        // https://docs.github.com/en/rest/repos/rules?apiVersion=2022-11-28#get-all-repository-rulesets
        $rulesets = github_api("https://api.github.com/repos/$account/$repo/rulesets");
        $branchRulesetID = null;
        $tagRulesetID = null;
        foreach ($rulesets as $ruleset) {
            $id = $ruleset['id'];
            $name = $ruleset['name'];
            if ($name === BRANCH_RULESET_NAME) {
                $branchRulesetID = $id;
            }
            if ($name === TAG_RULESET_NAME) {
                $tagRulesetID = $id;
            }
        }

        // Get any additional branches to add
        // Assumption is that if the default branch is main/master, then the repo uses
        // a non-numeric style branching system (e.g. main, master) and that needs to be protected
        // [0-9]* branch protection will still be applied, on the chance that the repo is converted
        // to uses a numeric style branch system in the future and we would want branch protection
        // to start immediately on the new branches
        $additionalBranchConditions = [];
        // https://docs.github.com/en/rest/repos/repos?apiVersion=2022-11-28#get-a-repository
        $defaultBranch = github_api("https://api.github.com/repos/$account/$repo")['default_branch'];
        if (in_array($defaultBranch, ['main', 'master'])) {
            $additionalBranchConditions[] = "refs/heads/$defaultBranch";
        }

        // Create rulesets
        // Note: This will read from the "rulesets" directory
        // In each of those json rulesets there is "bypass_actors"."actor_id" = 5
        // This translates to the "Repository admin" role
        // It has been confirmed that the github-action user is able to bypass the ruleset as
        // it has the "Organisation admin" role which is one level above the "Repository admin" role
        $branchRuleset = create_ruleset('branch', $additionalBranchConditions);
        $tagRuleset = create_ruleset('tag');

        $apiCalls = [];

        // Create new rulesets
        if (is_null($branchRulesetID)) {
            // https://docs.github.com/en/rest/repos/rules?apiVersion=2022-11-28#create-a-repository-ruleset
            $url = "https://api.github.com/repos/$account/$repo/rulesets";
            $apiCalls[] = [$url, $branchRuleset, 'POST'];
        }
        if (is_null($tagRulesetID)) {
            // https://docs.github.com/en/rest/repos/rules?apiVersion=2022-11-28#create-a-repository-ruleset
            $url = "https://api.github.com/repos/$account/$repo/rulesets";
            $apiCalls[] = [$url, $tagRuleset, 'POST'];
        }

        // Update existing rulesets
        // Don't bother to check if the ruleset is already correct
        // This is a very quick update so no need to optimise this
        if (!is_null($branchRulesetID)) {
            // https://docs.github.com/en/rest/repos/rules?apiVersion=2022-11-28#update-a-repository-ruleset
            $url = "https://api.github.com/repos/$account/$repo/rulesets/$branchRulesetID";
            $apiCalls[] = [$url, $branchRuleset, 'PUT'];
        }
        if (!is_null($tagRulesetID)) {
            // https://docs.github.com/en/rest/repos/rules?apiVersion=2022-11-28#update-a-repository-ruleset
            $url = "https://api.github.com/repos/$account/$repo/rulesets/$tagRulesetID";
            $apiCalls[] = [$url, $tagRuleset, 'PUT'];
        }

        if ($input->getOption('dry-run')) {
            info('Not updating rulesets on GitHub because --dry-run option is set');
            info('There API calls would have been made:');
            foreach ($apiCalls as $apiCall) {
                info($apiCall[2] . ' ' . $apiCall[0]);
            }
        } else {
            foreach ($apiCalls as $apiCall) {
                github_api($apiCall[0], $apiCall[1], $apiCall[2]);
            }
        }

        $REPOS_WITH_RULESETS_UPDATED[] = $repo;
    }

    output_repos_with_rulesets_created_or_updated();
    return Command::SUCCESS;
};
