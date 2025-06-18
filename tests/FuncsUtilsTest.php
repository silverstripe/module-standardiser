<?php

use PHPUnit\Framework\TestCase;
use SilverStripe\SupportedModules\MetaData;

class FuncsUtilsTest extends TestCase
{
    /**
     * @dataProvider provideBranchToCheckout
     */
    public function testBranchToCheckout(
        $expected,
        $branches,
        $defaultBranch,
        $currentBranch,
        $currentBranchCmsMajor,
        $cmsMajor,
        $branchOption
    ) {
        $actual = branch_to_checkout($branches, $defaultBranch, $currentBranch, $currentBranchCmsMajor, $cmsMajor, $branchOption);
        $this->assertSame($expected, $actual);
    }

    public function provideBranchToCheckout()
    {
        $branches = ['1.5', '1.6', '1', '2.0', '2.1' , '2.2', '2', '3', 'pulls/2.3/something', 'random'];
        return [
            ['2', $branches, '2', '2', '5', '5', 'next-minor'],
            ['2.2', $branches, '2', '2', '5', '5', 'next-patch'],
            ['1', $branches, '2', '2', '5', '4', 'next-minor'],
            ['1.6', $branches, '2', '2', '5', '4', 'next-patch'],
            ['2', $branches, '2', '1', '4', '5', 'next-minor'],
            ['2.2', $branches, '2', '1', '4', '5', 'next-patch'],
            ['3', $branches, '2', '1', '4', '6', 'next-minor'],
            ['2', $branches, '2', '1', '4', '6', 'github-default'],
        ];
    }

    /**
     * @dataProvider provideCurrentBranchCmsMajor
     */
    public function testCurrentBranchCmsMajor($expected, $composerJson)
    {
        global $GITHUB_REF;
        $GITHUB_REF = 'random/repo';
        $actual = current_branch_cms_major($composerJson);
        $this->assertSame($expected, $actual);
    }

    public function provideCurrentBranchCmsMajor()
    {
        $lowestMajor = MetaData::LOWEST_SUPPORTED_CMS_MAJOR;
        $highestMajor = MetaData::HIGHEST_STABLE_CMS_MAJOR;
        $lowestMajorPhpVersions = MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$lowestMajor];
        $highestMajorPhpVersions = MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$highestMajor];
        $scenarios = [
            'lowest major with minor' => [$lowestMajor, json_encode(['require' => ['silverstripe/framework' => "^{$lowestMajor}.13"]])],
            'highest major with minor' => [$highestMajor, json_encode(['require' => ['silverstripe/framework' => "^{$highestMajor}.0"]])],
            'highest major cms' => [$highestMajor, json_encode(['require' => ['silverstripe/cms' => '^' . $highestMajor]])],
            'highest major mfa' => [$highestMajor, json_encode(['require' => ['silverstripe/mfa' => '^' . $highestMajor]])],
            'lowest major offset' => [$lowestMajor, json_encode(['require' => ['silverstripe/assets' => '^' . (string)($lowestMajor - 3)]])],
            'highest major offset' => [$highestMajor, json_encode(['require' => ['silverstripe/assets' => '^' . (string)($highestMajor - 3)]])],
            'lowest major php version' => [$lowestMajor, json_encode(['require' => ['php' => '^' . $lowestMajorPhpVersions[0]]])],
            'highest major php version' => [$highestMajor, json_encode(['require' => ['php' => '^'  . $highestMajorPhpVersions[0]]])],
            'old PHP dep - default to highest major' => [$highestMajor, json_encode(['require' => ['php' => '^7.4']])],
            'no supported module - default to highest major' => [$highestMajor, json_encode(['require' => ['silverstripe/lorem-ipsum' => '^2']])],
        ];

        $nextMajor = (string)($highestMajor + 1);
        // Make sure we can deal with pre-release majors
        if (array_key_exists($nextMajor, MetaData::PHP_VERSIONS_FOR_CMS_RELEASES)) {
            $nextMajorPhpVersions = MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$nextMajor];
            $scenarios['next major supported module'] = [$nextMajor, json_encode(['require' => ['silverstripe/framework' => '^' . $nextMajor]])];
            $scenarios['next major php version'] = [$nextMajor, json_encode(['require' => ['php' => '^' . $nextMajorPhpVersions[0]]])];
        }
        return $scenarios;
    }
}

