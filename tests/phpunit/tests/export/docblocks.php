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
	 * Test that hooks which aren't documented don't receive docs from another node.
	 */
	public function test_undocumented_hook() {

		$this->assertHookHasDocs(
			'undocumented_hook',
			array(
				'raw' => '',
				'summary' => '',
			)
		);
	}

	/**
	 * Test that hook docbloks are picked up.
	 */
	public function test_hook_docblocks() {

		$this->assertHookHasDocs(
			'test_action',
			array(
				'raw' => $this->make_docblock( array(
					'A test action.',
					'',
					'@since 3.7.0',
					'',
					'@param WP_Post $post Post object.',
				) ),
				'summary' => 'A test action.',
				'tags' => array(
					array(
						'name' => 'since',
						'content' => '3.7.0',
					),
					array(
						'name' => 'param',
						'types' => array( '\WP_Post' ),
						'variable' => '$post',
						'content' => 'Post object.'
					)
				)
			)
		);

		$this->assertHookHasDocs(
			'test_filter',
			array(
				'raw' => $this->make_docblock( array( 'A filter.' ) ),
				'summary' => 'A filter.',
			)
		);

		$this->assertHookHasDocs(
			'test_ref_array_action',
			array(
				'raw' => $this->make_docblock( array( 'A reference array action.' ) ),
				'summary' => 'A reference array action.',
			)
		);

		$this->assertHookHasDocs(
			'test_ref_array_filter',
			array(
				'raw' => $this->make_docblock( array( 'A reference array filter.' ) ),
				'summary' => 'A reference array filter.'
			)
		);
	}

	/**
	 * Test that file-level docs are exported.
	 */
	public function test_file_docblocks() {
		$this->assertFileHasDocs(
			array(
				'raw' => $this->make_docblock( array(
					'This is the file-level docblock summary.',
					'',
					'This is the file-level docblock description, which may span multiple lines. In',
					'fact, this one does. It spans more than two full lines, continuing on to the',
					'third line.',
					'',
					'@since 1.5.0',
				) ),
				'summary' => 'This is the file-level docblock summary.',
				'description' => $this->multiline_string( array(
					'<p>This is the file-level docblock description, which may span multiple lines. In',
					'fact, this one does. It spans more than two full lines, continuing on to the',
					'third line.</p>',
				) ),
				'raw_description' => $this->multiline_string( array(
					'This is the file-level docblock description, which may span multiple lines. In',
					'fact, this one does. It spans more than two full lines, continuing on to the',
					'third line.',
				) ),
				'tags' => array(
					array(
						'name' => 'since',
						'content' => '1.5.0',
					)
				)
			)
		);
	}

	/**
	 * Test that function docs are exported.
	 */
	public function test_function_docblocks() {

		$this->assertFunctionHasDocs(
			'test_func',
			array(
				'raw' => $this->make_docblock( array(
					'This is a function docblock.',
					'',
					'This function is just a test, but we\'ve added this description anyway.',
					'',
					'@since 2.6.0',
					'',
					'@param string $var A string value.',
					'@param int    $num A number.',
					'',
					'@return bool Whether the function was called correctly.',
				) ),
				'summary' => 'This is a function docblock.',
				'raw_description' => 'This function is just a test, but we\'ve added this description anyway.',
				'description' => '<p>This function is just a test, but we\'ve added this description anyway.</p>',
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
			'Test_Class',
			array(
				'raw' => $this->make_docblock( array(
					'This is a class docblock.',
					'',
					'This is the more wordy description: This is a comment with two *\'s at the start,',
					'which means that it is a doc comment. Docblock comments are comment blocks used',
					'to document code. This one documents the Test_Class class.',
					'',
					'@since 3.5.2',
				) ),
				'summary' => 'This is a class docblock.',
				'description' => $this->multiline_string( array(
					'<p>This is the more wordy description: This is a comment with two *\'s at the start,',
					'which means that it is a doc comment. Docblock comments are comment blocks used',
					'to document code. This one documents the Test_Class class.</p>',
				) ),
				'raw_description' => $this->multiline_string( array(
					'This is the more wordy description: This is a comment with two *\'s at the start,',
					'which means that it is a doc comment. Docblock comments are comment blocks used',
					'to document code. This one documents the Test_Class class.',
				) ),
				'tags' => array(
					array(
						'name' => 'since',
						'content' => '3.5.2',
					)
				)
			)
		);
	}

	/**
	 * Test that method docs are exported.
	 */
	public function test_method_docblocks() {

		$this->assertMethodHasDocs(
			'Test_Class',
			'test_method',
			array(
				'raw' => $this->make_docblock( array(
					'This is a method docblock.',
					'',
					'@since 4.5.0',
					'',
					'@param mixed $var A parameter.',
					'@param array $arr Another parameter.',
					'',
					'@return mixed The first param.',
				), "\t" ),
				'summary' => 'This is a method docblock.',
				'tags' => array(
					array(
						'name' => 'since',
						'content' => '4.5.0',
					),
					array(
						'name' => 'param',
						'types' => array( 'mixed' ),
						'variable' => '$var',
						'content' => 'A parameter.',
					),
					array(
						'name' => 'param',
						'types' => array( 'array' ),
						'variable' => '$arr',
						'content' => 'Another parameter.'
					),
					array(
						'name' => 'return',
						'types' => array( 'mixed' ),
						'content' => 'The first param.'
					),
				),
			)
		);
	}

	/**
	 * Test that function docs are exported.
	 */
	public function test_property_docblocks() {

		$this->assertPropertyHasDocs(
			'Test_Class',
			'$a_string',
			array(
				'summary' => 'This is a docblock for a class property.'
			)
		);
	}
}
