<?php

/**
 * Test case for @type tag handling in docblocks.
 */

namespace WP_Parser\Tests;

/**
 * Test that @type tags are handled correctly.
 *
 * The @type tag should NOT be extracted as a separate top-level tag.
 * Instead, it should remain embedded within the @param content.
 */
class Export_Type_Tags extends Export_UnitTestCase {

	/**
	 * Test that @type is NOT extracted as a separate tag and is preserved in @param.
	 */
	public function test_type_not_extracted_as_separate_tag() {
		$this->assertFunctionHasDocs(
			'function_with_hash_notation',
			array(
				'description' => 'Function with hash notation in @param.',
				'tags' => array(
					array(
						'name' => 'since',
						'content' => '1.0.0',
					),
					array(
						'name' => 'param',
						'content' => '{ Optional. Array of arguments.<br>@type bool   $enabled   Whether the feature is enabled. Default false.<br>@type string $label     The label to display.<br>@type int    $max_items Maximum number of items. Default 10.<br>}',
						'types' => array( 'array' ),
						'variable' => '$args',
					),
					array(
						'name' => 'return',
						'content' => 'True on success.',
						'types' => array( 'bool' ),
					),
				),
			)
		);
	}

	/**
	 * Test multiple hash notation params.
	 */
	public function test_multiple_hash_params() {
		$this->assertFunctionHasDocs(
			'function_with_multiple_hash_params',
			array(
				'description' => 'Function with multiple hash notation params.',
				'tags' => array(
					array(
						'name' => 'since',
						'content' => '2.0.0',
					),
					array(
						'name' => 'param',
						'content' => '{ Configuration options.<br>@type string $name   The name.<br>@type int    $count  The count.<br>}',
						'types' => array( 'array' ),
						'variable' => '$options',
					),
					array(
						'name' => 'param',
						'content' => '{ Additional settings.<br>@type bool $active Whether active.<br>}',
						'types' => array( 'array' ),
						'variable' => '$settings',
					),
					array(
						'name' => 'return',
						'content' => '',
						'types' => array( 'void' ),
					),
				),
			)
		);
	}

	/**
	 * Test simple params without hash notation still work.
	 */
	public function test_simple_params_work() {
		$this->assertFunctionHasDocs(
			'function_with_simple_params',
			array(
				'description' => 'Function with simple params (no hash notation).',
				'tags' => array(
					array(
						'name' => 'since',
						'content' => '1.0.0',
					),
					array(
						'name' => 'param',
						'content' => 'The name.',
						'types' => array( 'string' ),
						'variable' => '$name',
					),
					array(
						'name' => 'param',
						'content' => 'The count.',
						'types' => array( 'int' ),
						'variable' => '$count',
					),
					array(
						'name' => 'return',
						'content' => 'Success.',
						'types' => array( 'bool' ),
					),
				),
			)
		);
	}

	/**
	 * Test @type in @return tag is preserved.
	 */
	public function test_type_preserved_in_return() {
		$this->assertFunctionHasDocs(
			'function_with_return_hash',
			array(
				'description' => 'Function with hash notation in @return.',
				'tags' => array(
					array(
						'name' => 'since',
						'content' => '1.0.0',
					),
					array(
						'name' => 'return',
						'content' => '{ Result data.<br>@type int[]    $updated An array of updated IDs.<br>@type int[]    $skipped An array of skipped IDs.<br>@type string[] $errors  An array of error messages.<br>}',
						'types' => array( 'array' ),
					),
				),
			)
		);
	}

	/**
	 * Test @type in both @param and @return are preserved correctly.
	 */
	public function test_type_in_param_and_return() {
		$this->assertFunctionHasDocs(
			'function_with_param_and_return_hash',
			array(
				'description' => 'Function with hash notation in both @param and @return.',
				'tags' => array(
					array(
						'name' => 'since',
						'content' => '1.0.0',
					),
					array(
						'name' => 'param',
						'content' => '{ Input options.<br>@type string $mode   The processing mode.<br>@type bool   $force  Whether to force the operation.<br>}',
						'types' => array( 'array' ),
						'variable' => '$options',
					),
					array(
						'name' => 'return',
						'content' => '{ Output data.<br>@type bool   $success Whether the operation succeeded.<br>@type string $message A status message.<br>}',
						'types' => array( 'array' ),
					),
				),
			)
		);
	}

	/**
	 * Test mixed params: some with hash notation, some without.
	 */
	public function test_mixed_params() {
		$this->assertFunctionHasDocs(
			'function_with_mixed_params',
			array(
				'description' => 'Function with mixed params: some with hash, some without.',
				'tags' => array(
					array(
						'name' => 'since',
						'content' => '1.0.0',
					),
					array(
						'name' => 'param',
						'content' => 'Simple string param.',
						'types' => array( 'string' ),
						'variable' => '$name',
					),
					array(
						'name' => 'param',
						'content' => '{ Configuration array.<br>@type int  $timeout Timeout in seconds.<br>@type bool $retry   Whether to retry on failure.<br>}',
						'types' => array( 'array' ),
						'variable' => '$config',
					),
					array(
						'name' => 'param',
						'content' => 'Simple int param.',
						'types' => array( 'int' ),
						'variable' => '$limit',
					),
					array(
						'name' => 'return',
						'content' => 'Success.',
						'types' => array( 'bool' ),
					),
				),
			)
		);
	}
}
