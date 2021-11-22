<?php

/**
 * A test case for exporting docblocks.
 */

namespace WP_Parser\Tests;

/**
 * Test that docblocks are exported correctly.
 */
class Export_Docblocks extends Export_UnitTestCase
{
    /**
     * Test that line breaks are removed when the description is exported.
     */
    public function test_linebreaks_removed() {
        $this->assertStringMatchesFormat(
            '%s',
            $this->export_data['classes'][0]['doc']['long_description']
        );
    }

    /**
     * Test that hooks which aren't documented don't receive docs from another node.
     */
    public function test_undocumented_hook() {
        $this->assertHookHasDocs(
            'undocumented_hook',
            [
                'description' => '',
            ]
        );
    }

    /**
     * Test that hook docbloks are picked up.
     */
    public function test_hook_docblocks() {
        $this->assertHookHasDocs(
            'test_action',
            ['description' => 'A test action.']
        );

        $this->assertHookHasDocs(
            'test_filter',
            ['description' => 'A filter.']
        );

        $this->assertHookHasDocs(
            'test_ref_array_action',
            ['description' => 'A reference array action.']
        );

        $this->assertHookHasDocs(
            'test_ref_array_filter',
            ['description' => 'A reference array filter.']
        );
    }

    /**
     * Test that file-level docs are exported.
     */
    public function test_file_docblocks() {
        $this->assertFileHasDocs(
            ['description' => 'This is the file-level docblock summary.']
        );
    }

    /**
     * Test that function docs are exported.
     */
    public function test_function_docblocks() {
        $this->assertFunctionHasDocs(
            'test_func',
            [
                'description' => 'This is a function docblock.',
                'long_description' => '<p>This function is just a test, but we\'ve added this description anyway.</p>',
                'tags' => [
                    [
                        'name' => 'since',
                        'content' => '2.6.0',
                    ],
                    [
                        'name' => 'param',
                        'content' => 'A string value.',
                        'types' => ['string'],
                        'variable' => '$var',
                    ],
                    [
                        'name' => 'param',
                        'content' => 'A number.',
                        'types' => ['int'],
                        'variable' => '$num',
                    ],
                    [
                        'name' => 'return',
                        'content' => 'Whether the function was called correctly.',
                        'types' => ['bool'],
                    ],
                ],
            ]
        );
    }

    /**
     * Test that class docs are exported.
     */
    public function test_class_docblocks() {
        $this->assertClassHasDocs(
            'Test_Class',
            ['description' => 'This is a class docblock.']
        );
    }

    /**
     * Test that method docs are exported.
     */
    public function test_method_docblocks() {
        $this->assertMethodHasDocs(
            'Test_Class',
            'test_method',
            ['description' => 'This is a method docblock.']
        );
    }

    /**
     * Test that function docs are exported.
     */
    public function test_property_docblocks() {
        $this->assertPropertyHasDocs(
            'Test_Class',
            '$a_string',
            ['description' => 'This is a docblock for a class property.']
        );
    }
}
