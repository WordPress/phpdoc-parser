<?php

/**
 * A test case for exporting docblocks.
 */

namespace WP_Parser\Tests;

/**
 * Test that docblocks are exported correctly.
 */
class Export_Docblocks extends Export_UnitTestCase {

	/**
	 * Test that line breaks are removed when the description is exported.
	 */
	public function test_linebreaks_removed() {

		$this->assertStringMatchesFormat(
			'%s'
			, $this->export_data['classes'][0]['doc']['long_description']
		);
	}

	/**
	 * Test that hooks which aren't documented don't receive docs from another node.
	 */
	public function test_undocumented_hook() {

		$this->assertHookHasDocs(
			'undocumented_hook'
			, array(
				'description' => '',
			)
		);
	}

	/**
	 * Test that hook docbloks are picked up.
	 */
	public function test_hook_docblocks() {

		$this->assertHookHasDocs(
			'test_action'
			, array( 'description' => 'A test action.' )
		);

		$this->assertHookHasDocs(
			'test_filter'
			, array( 'description' => 'A filter.' )
		);

		$this->assertHookHasDocs(
			'test_ref_array_action'
			, array( 'description' => 'A reference array action.' )
		);

		$this->assertHookHasDocs(
			'test_ref_array_filter'
			, array( 'description' => 'A reference array filter.' )
		);
	}

	/**
	 * Test that file-level docs are exported.
	 */
	public function test_file_docblocks() {

		$this->assertFileHasDocs(
			array( 'description' => 'This is the file-level docblock summary.' )
		);
	}

	/**
	 * Test that function docs are exported.
	 */
	public function test_function_docblocks() {

		$this->assertFunctionHasDocs(
			'test_func'
			, array(
				'description' => 'This is a function docblock.',
				'long_description' => '<p>This function is just a test, but we\'ve added this description anyway.</p>',
				'tags' => array(
					array(
						'name' => 'since',
						'content' => '2.6.0',
					),
					array(
						'name' => 'param',
						'content' => 'A string value.',
						'types' => array( 'string' ),
						'variable' => '$var',
					),
					array(
						'name' => 'param',
						'content' => 'A number.',
						'types' => array( 'int' ),
						'variable' => '$num',
					),
					array(
						'name' => 'return',
						'content' => 'Whether the function was called correctly.',
						'types' => array( 'bool' ),
					),
				),
			)
		);
	}

	/**
	 * Test that class docs are exported.
	 */
	public function test_class_docblocks() {

		$this->assertClassHasDocs(
			'Test_Class'
			, array( 'description' => 'This is a class docblock, the summary of the class is spread over multiple lines.' )
		);
	}

	/**
	 * Test that method docs are exported.
	 */
	public function test_method_docblocks() {

		$this->assertMethodHasDocs(
			'Test_Class'
			, 'test_method'
			, array( 'description' => 'This is a method docblock.' )
		);
	}

	/**
	 * Test that function docs are exported.
	 */
	public function test_property_docblocks() {

		$this->assertPropertyHasDocs(
			'Test_Class'
			, '$a_string'
			, array( 'description' => 'This is a docblock for a class property.' )
		);
	}

	/**
	 * Test that `@see` tags are exported correctly.
	 */
	function test_method_see() {
		$this->assertMethodHasDocs(
			'Test_Class'
			, 'test_method_see'
			, array(
				'tags' => array(
					array(
						'name' => 'see',
						'refers' => 'self::test_method_typed_hash()',
						'content' => '',
					),
					array(
						'name' => 'see',
						'refers' => 'https://wordpressfoundation.org/',
						'content' => 'The WordPress Foundation.',
					),
				),
			)
		);
	}

	/**
	 * Test the many types of typed parameters we can expect.
	 */
	public function test_method_typed_hash() {
		$this->assertMethodHasDocs(
			'Test_Class'
			, 'test_method_typed_hash'
			, array(
				'tags' => array(
					array(
						'name' => 'param',
						'types' => array( 'array' ),
						'variable' => '$hashed_array',
						// @see https://github.com/WordPress/wporg-developer/blob/bcb196110099a2cd898230834022b6237917e793/source/wp-content/themes/wporg-developer-2023/inc/formatting.php#L598-L680
						'content' => '{ The parameters for this function.<br>@type int $time The current epoch.<br>@type ?string $nullable_string A nullable string.<br>@type string|array List of items: <ul> <li>\'item1\'</li> <li>\'item2\' Default is \'item1\'.<br>}</li> </ul>',
					),
					array(
						'name' => 'param',
						'types' => array( 'WP_Post', 'WP_User' ),
						'variable' => '$post_or_user',
						'content' => 'A Post or User.',
					),
					array(
						'name' => 'param',
						'types' => array( 'WP_Post', 'null' ),
						'variable' => '$nullable_post',
						'content' => 'A Nullable post.',
					),
					array(
						'name' => 'param',
						'types' => array( '?string' ),
						'variable' => '$nullable_string',
						'content' => 'A nullable string.',
					),
					array(
						'name' => 'return',
						'content' => 'An empty array.',
						'types' => array( 'array' ),
					),
				)
			)
		);
	}

	/**
	 * Test that markdown in descriptions are marked up.
	 */
	function test_markdown_in_description() {
		$description = $this->export_data['functions'][1]['doc']['long_description'];

		$this->assertStringContainsString( '<code>code</code>', $description );
		$this->assertStringContainsString( '<em>italics</em>', $description );
		$this->assertStringContainsString( '<strong>bold</strong>', $description );
		$this->assertStringContainsString( '<li>Item 1</li>', $description );
		$this->assertStringContainsString( '<li>Item 2</li>', $description );
		$this->assertStringContainsString( '<pre><code>foo();', $description );
		$this->assertStringContainsString( '<blockquote>', $description );
		$this->assertStringContainsString( '<h2>Inline Formatting includes Headings.</h2>', $description );
	}
}
