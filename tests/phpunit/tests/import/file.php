<?php

/**
 * Test for importing files.
 */

namespace WP_Parser\Tests;

/**
 * Test that files are imported correctly.
 *
 * @group import
 */
class File_Import_Test extends Import_UnitTestCase
{
    /**
     * Test that the term is created for this file.
     */
    public function test_file_term_created() {
        $terms = get_terms(
            $this->importer->taxonomy_file,
            ['hide_empty' => false]
        );

        $this->assertCount(1, $terms);

        $term = $terms[0];

        $this->assertEquals('file.inc', $term->name);
        $this->assertEquals('file-inc', $term->slug);
    }

    /**
     * Test that a post is created for the function.
     */
    public function test_function_post_created() {
        $posts = get_posts(
            ['post_type' => $this->importer->post_type_function]
        );

        $this->assertCount(1, $posts);

        $post = $posts[0];

        // Check that the post attributes are correct.
        $this->assertEquals(
            '<p>This function is just here for tests. This is its longer description.</p>',
            $post->post_content
        );
        $this->assertEquals('This is a function summary.', $post->post_excerpt);
        $this->assertEquals('wp_parser_test_func', $post->post_name);
        $this->assertEquals(0, $post->post_parent);
        $this->assertEquals('wp_parser_test_func', $post->post_title);

        // It should be assigned to the file's taxonomy term.
        $terms = wp_get_object_terms(
            $post->ID,
            $this->importer->taxonomy_file
        );

        $this->assertCount(1, $terms);
        $this->assertEquals('file.inc', $terms[0]->name);

        // It should be assigned to the correct @since taxonomy term.
        $terms = wp_get_object_terms(
            $post->ID,
            $this->importer->taxonomy_since_version
        );

        $this->assertCount(1, $terms);
        $this->assertEquals('1.4.0', $terms[0]->name);

        // It should be assigned the correct @package taxonomy term.
        $terms = wp_get_object_terms(
            $post->ID,
            $this->importer->taxonomy_package
        );

        $this->assertCount(1, $terms);
        $this->assertEquals('Something', $terms[0]->name);

        // Check that the metadata was imported.
        $this->assertEquals(
            [
                [
                    'name' => '$var',
                    'default' => null,
                    'type' => '',
                ],
                [
                    'name' => '$ids',
                    'default' => 'array()',
                    'type' => 'array',
                ],
            ],
            get_post_meta($post->ID, '_wp-parser_args', true)
        );

        $this->assertEquals(
            25,
            get_post_meta($post->ID, '_wp-parser_line_num', true)
        );

        $this->assertEquals(
            28,
            get_post_meta($post->ID, '_wp-parser_end_line_num', true)
        );

        $this->assertEquals(
            [
                [
                    'name' => 'since',
                    'content' => '1.4.0',
                ],
                [
                    'name' => 'param',
                    'content' => 'A string variable which is the first parameter.',
                    'types' => ['string'],
                    'variable' => '$var',
                ],
                [
                    'name' => 'param',
                    'content' => 'An array of user IDs.',
                    'types' => ['int[]'],
                    'variable' => '$ids',
                ],
                [
                    'name' => 'return',
                    'content' => 'The return type is random. (Not really.)',
                    'types' => ['mixed'],
                ],
            ],
            get_post_meta($post->ID, '_wp-parser_tags', true)
        );
    }
}
