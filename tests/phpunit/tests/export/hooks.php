<?php

/**
 * A test case for hook exporting.
 */

namespace WP_Parser\Tests;

/**
 * Test that hooks are exported correctly.
 */
class Export_Hooks extends Export_UnitTestCase {

	/**
	 * Test that hook names are standardized on export.
	 */
	public function test_hook_names_standardized() {

		$this->assertFileContainsHook(
			array( 'name' => 'plain_action', 'line' => 3 )
		);

		$this->assertFileContainsHook(
			array( 'name' => 'action_with_double_quotes', 'line' => 4 )
		);

		$this->assertFileContainsHook(
			array( 'name' => '{$variable}-action', 'line' => 5 )
		);

		$this->assertFileContainsHook(
			array( 'name' => 'another-{$variable}-action', 'line' => 6 )
		);

		$this->assertFileContainsHook(
			array( 'name' => 'hook_{$object->property}_pre', 'line' => 7 )
		);

		$this->assertFileContainsHook(
			array(
				'type' => 'filter',
				'name' => 'plain_filter',
				'line' => 8,
				'arguments.0' => '$variable',
				'arguments.1' => '$filter_context'
			)
		);

		$this->assertFileContainsHook(
			array( 'name' => '\\xC0 hook', 'line' => 9 )
		);

		$this->assertFileContainsHook(
			array( 'name' => '\\x09tab', 'line' => 10 )
		);

		$this->assertFileContainsHook(
			array( 'name' => '\\x09tab', 'line' => 11 )
		);
	}

	/**
	 * Test that hook names keep escapes that are invalid UTF-8 once interpreted.
	 */
	public function test_hook_names_keep_invalid_utf8_escapes() {

		$this->assertFileContainsHook(
			array( 'name' => '\\xC0 bad', 'line' => 12 )
		);
	}

	/**
	 * Test that heredoc and nowdoc arguments keep their delimiters.
	 */
	public function test_hook_arguments_keep_doc_string_delimiters() {

		$this->assertFileContainsHook(
			array(
				'type' => 'filter',
				'name' => 'heredoc_filter',
				'line' => 13,
				'arguments.0' => "<<<EOT\nheredoc body\nEOT",
				'arguments.1' => '2',
			)
		);

		$this->assertFileContainsHook(
			array(
				'type' => 'filter',
				'name' => 'nowdoc_filter',
				'line' => 16,
				'arguments.0' => "<<<'EOT'\nnowdoc \$body\nEOT",
				'arguments.1' => '2',
			)
		);
	}

	/**
	 * Test that the exported data can be encoded as JSON.
	 */
	public function test_export_data_is_json_encodable() {

		$this->assertNotFalse(
			json_encode( $this->export_data, JSON_PRETTY_PRINT ),
			json_last_error_msg()
		);
	}
}
