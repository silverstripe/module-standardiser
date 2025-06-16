<?php

use SilverStripe\SupportedModules\MetaData;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

// ! IMPORTANT !
// - Any labels on the GitHub repo that are not defined in LABELS_COLORS will be deleted
// - Any labels in LABELS_COLORS that do not exist on the GitHub repo will be created
//
// Do not prefix color with hash i.e. use 'cccccc' not '#cccccc'
const LABELS_COLORS = [
    'affects/v5' => '0e8a16',
    'affects/v6' => 'baee47',
    'complexity/low' => 'c2e0c6',
    'complexity/medium' => 'fef2c0',
    'complexity/high' => 'f9d0c4',
    'Epic' => '3e4b9e',
    'impact/low' => 'fef2c0',
    'impact/medium' => 'f7c6c7',
    'impact/high' => 'eb6420',
    'impact/critical' => 'e11d21',
    'rfc/accepted' => 'dddddd',
    'rfc/draft' => 'dddddd',
    'type/api-break' => '1d76db',
    'type/bug' => 'd93f0b',
    'type/docs' => '02d7e1',
    'type/enhancement' => '0e8a16',
    'type/userhelp' => 'c5def5',
    'type/UX' => '006b75',
    'type/other' => '975515',
    'type/i18n' => 'ae177d',
];

// Rename existing labels 'from' => 'to'
const LABELS_RENAME = [
    'effort/easy' => 'complexity/low',
    'effort/medium' => 'complexity/medium',
    'effort/hard' => 'complexity/high',
    'change/major' => 'type/api-break',
    'type/api-change' => 'type/api-break',
];

// Repos that should not have labels updated because of a lack of API permissions because they are on
// non-silverstripe GitHub accounts, or because they are not applicable
const LABELS_EXCLUDE_GHREPOS = [
    'composer/installers',
    'dnadesign/silverstripe-elemental-subsites',
    'hafriedlander/phockito',
    'hafriedlander/silverstripe-phockito',
    'lekoala/silverstripe-debugbar',
    'tijsverkoyen/akismet',
    'tractorcow-farm/silverstripe-fluent',
    'tractorcow/classproxy',
    'tractorcow/silverstripe-proxy-db',
    'undefinedoffset/sortablegridfield',
];

$labelsCommand = function(InputInterface $input, OutputInterface $output): int {
    // This is the code that is executed when running the 'labels' command

    // variables
    global $OUT, $REPOS_WITH_LABELS_UPDATED;
    $OUT = $output;

    // validate system is ready
    validate_system();

    // setup directories
    setup_directories($input, [DATA_DIR]);

    // modules
    $modules = [];
    $repos = [];
    $modulesCurrentMajor = filtered_modules(MetaData::HIGHEST_STABLE_CMS_MAJOR, $input);
    $modulesPreviousMajor = filtered_modules(MetaData::HIGHEST_STABLE_CMS_MAJOR - 1, $input);
    foreach ([$modulesCurrentMajor, $modulesPreviousMajor] as $modulesList) {
        foreach ($modulesList as $module) {
            $repo = $module['repo'];
            $ghrepo = $module['ghrepo'];
            if (in_array($repo, $repos) || in_array($ghrepo, LABELS_EXCLUDE_GHREPOS)) {
                continue;
            }
            $modules[] = $module;
            $repos[] = $repo;
        }
    }

    // update labels
    foreach ($modules as $module) {
        $account = $module['account'];
        $repo = $module['repo'];

        // Fetch labels
        $labels = github_api("https://api.github.com/repos/$account/$repo/labels");

        foreach ($labels as $key => $label) {
            // $label is an array, for example:
            // 'id' => 427423377
            // 'node_id' => MDU6TGFiZWw0Mjc0MjMzNzc="
            // 'url' => "https://api.github.com/repos/silverstripe/silverstripe-config/labels/affects/v4"
            // 'name' => "affects/v4"
            // 'color' => "5319e7"
            // 'default' => false
            // 'description' => NULL
            $url = $label['url'];
            $name = $label['name']; // e.g. 'affects/v4'

            // Rename label
            // https://docs.github.com/en/rest/issues/labels#update-a-label
            if (array_key_exists($name, LABELS_RENAME)) {
                $newName = LABELS_RENAME[$name];
                // Don't rename if a label with the new name already exists
                $alreadyExists = false;
                foreach ($labels as $label) {
                    if ($newName === $label['name']) {
                        $alreadyExists = true;
                        break;
                    }
                }
                if (!$alreadyExists) {
                    info("Updating label $name to $newName in $repo");
                    if ($input->getOption('dry-run')) {
                        info('Not updating label on GitHub because --dry-run option is set');
                    } else {
                        github_api($url, ['new_name' => $newName], 'PATCH');
                    }
                    $oldName = $name;
                    $name = $newName;
                    // Update $url replacing the $name at the end with $newName
                    $url = substr($url, 0, strlen($url) - strlen($oldName)) . $newName;
                    $labels[$key]['name'] = $newName;
                    $labels[$key]['url'] = $url;
                }
            }

            // Delete label
            // https://docs.github.com/en/rest/issues/labels#delete-a-label
            if (!array_key_exists($name, LABELS_COLORS)) {
                info("Deleting label $name from $repo");
                if ($input->getOption('dry-run')) {
                    info('Not deleting label on GitHub because --dry-run option is set');
                } else {
                    github_api($url, [], 'DELETE');
                }
                continue;
            }

            // Update label color
            // https://docs.github.com/en/rest/issues/labels#update-a-label
            if (LABELS_COLORS[$name] !== $label['color']) {
                info("Updating label color $name on $repo");
                if ($input->getOption('dry-run')) {
                    info('Not updating label color on GitHub because --dry-run option is set');
                } else {
                    github_api($url, ['color' => LABELS_COLORS[$name]], 'PATCH');
                }
            }
        }

        // Create missing labels
        // https://docs.github.com/en/rest/issues/labels#create-a-label
        foreach (LABELS_COLORS as $name => $color) {
            foreach ($labels as $label) {
                if ($name === $label['name']) {
                    continue 2;
                }
            }
            info("Creating label $name on $repo");
            if ($input->getOption('dry-run')) {
                info('Not creating label on GitHub because --dry-run option is set');
            } else {
                $url = "https://api.github.com/repos/$account/$repo/labels";
                github_api($url, ['name' => $name,'color' => $color]);
            }
        }
        $REPOS_WITH_LABELS_UPDATED[] = $repo;
    }
    output_repos_with_labels_updated();
    return Command::SUCCESS;
};
