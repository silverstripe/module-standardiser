<?php

use PHPUnit\Framework\TestCase;

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
        $actual = current_branch_cms_major($composerJson);
        $this->assertSame($expected, $actual);
    }

    public function provideCurrentBranchCmsMajor()
    {
        return [
            ['4', json_encode(['require' => ['silverstripe/framework' => '^4.13']])],
            ['5', json_encode(['require' => ['silverstripe/framework' => '^5.0']])],
            ['6', json_encode(['require' => ['silverstripe/framework' => '^6']])],
            ['5', json_encode(['require' => ['silverstripe/cms' => '^5']])],
            ['5', json_encode(['require' => ['silverstripe/mfa' => '^5']])],
            ['4', json_encode(['require' => ['silverstripe/assets' => '^1']])],
            ['5', json_encode(['require' => ['silverstripe/assets' => '^2']])],
            ['4', json_encode(['require' => ['php' => '^7.4']])],
            ['5', json_encode(['require' => ['php' => '^8.1']])],
            ['', json_encode(['require' => ['silverstripe/lorem-ipsum' => '^2']])],
        ];
    }
}

