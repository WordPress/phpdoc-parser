<?php

/**
 * A test case for hook exporting.
 */

namespace WP_Parser\Tests;

/**
 * Test that hooks are exported correctly.
 */
class Export_Hooks extends Export_UnitTestCase
{
    /**
     * Test that hook names are standardized on export.
     */
    public function test_hook_names_standardized() {
        $this->assertFileContainsHook(
            [
                'name' => 'plain_action',
                'line' => 3,
            ]
        );

        $this->assertFileContainsHook(
            [
                'name' => 'action_with_double_quotes',
                'line' => 4,
            ]
        );

        $this->assertFileContainsHook(
            [
                'name' => '{$variable}-action',
                'line' => 5,
            ]
        );

        $this->assertFileContainsHook(
            [
                'name' => 'another-{$variable}-action',
                'line' => 6,
            ]
        );

        $this->assertFileContainsHook(
            [
                'name' => 'hook_{$object->property}_pre',
                'line' => 7,
            ]
        );
    }
}
