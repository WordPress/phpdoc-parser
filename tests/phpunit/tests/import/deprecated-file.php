<?php

/**
 * Test for detecting deprecated files during import.
 */

namespace WP_Parser\Tests;

/**
 * Test that a file's _deprecated_file() call deprecates its items on import.
 *
 * @group import
 */
class Deprecated_File_Import_Test extends Export_UnitTestCase {

	/**
	 * Import the parsed fixture with the given file-level function uses.
	 *
	 * @param array $functions_used Export data for the file's function uses.
	 *
	 * @return array The imported function post's tags meta.
	 */
	protected function import_with_file_uses( $functions_used ) {

		$file_data = $this->export_data;
		$file_data['uses']['functions'] = $functions_used;

		$importer = new \WP_Parser\Importer;
		$importer->import( array( $file_data ) );

		$posts = get_posts(
			array(
				'post_type' => $importer->post_type_function,
				'name'      => 'wp_parser_deprecated_file_test_func',
			)
		);

		$this->assertCount( 1, $posts );

		return get_post_meta( $posts[0]->ID, '_wp-parser_tags', true );
	}

	/**
	 * Test that a file deprecated by its first call is detected.
	 */
	public function test_deprecating_first_call_is_detected() {

		$tags = $this->import_with_file_uses(
			array(
				array(
					'name'                => '_deprecated_file',
					'line'                => 3,
					'end_line'            => 3,
					'deprecation_version' => '2.0.0',
				),
			)
		);

		$this->assertArrayHasKey( 'deprecated', $tags );
		$this->assertSame( '2.0.0', $tags['deprecated'] );
	}

	/**
	 * Test that the deprecating call is detected after preceding calls.
	 */
	public function test_deprecating_call_after_other_calls_is_detected() {

		$tags = $this->import_with_file_uses(
			array(
				array(
					'name'     => 'do_something_first',
					'line'     => 3,
					'end_line' => 3,
				),
				array(
					'name'                => '_deprecated_file',
					'line'                => 4,
					'end_line'            => 4,
					'deprecation_version' => '2.0.0',
				),
			)
		);

		$this->assertArrayHasKey( 'deprecated', $tags );
		$this->assertSame( '2.0.0', $tags['deprecated'] );
	}

	/**
	 * Test that a file without a deprecating call is not deprecated.
	 */
	public function test_file_without_deprecating_call_is_not_deprecated() {

		$tags = $this->import_with_file_uses(
			array(
				array(
					'name'     => 'do_something_first',
					'line'     => 3,
					'end_line' => 3,
				),
			)
		);

		$this->assertArrayNotHasKey( 'deprecated', $tags );
	}

	/**
	 * Test that a deprecating call without a version does not deprecate.
	 */
	public function test_deprecating_call_without_version_is_not_deprecated() {

		$tags = $this->import_with_file_uses(
			array(
				array(
					'name'                => '_deprecated_file',
					'line'                => 3,
					'end_line'            => 3,
					'deprecation_version' => null,
				),
			)
		);

		$this->assertArrayNotHasKey( 'deprecated', $tags );
	}

	/**
	 * Test that a file with an empty uses list imports without error.
	 */
	public function test_empty_uses_list_is_not_deprecated() {

		$tags = $this->import_with_file_uses( array() );

		$this->assertArrayNotHasKey( 'deprecated', $tags );
	}
}
