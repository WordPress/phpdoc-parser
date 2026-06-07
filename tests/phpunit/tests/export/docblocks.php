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
			array(
				'description' => 'This is the file-level docblock summary.',
				'setup_blueprints' => array(
					'file-greeting' => array(
						'steps' => array(
							array(
								'step' => 'writeFile',
								'path' => '/wordpress/wp-content/mu-plugins/file-greeting.php',
								'data' => "<?php\nfunction docs_file_greeting() {\n\treturn 'Hello from the file setup';\n}\n",
							),
						),
					),
				),
			)
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
			, array( 'description' => 'This is a class docblock.' )
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
	 * Test that method code snippets are exported.
	 */
	public function test_method_code_snippets() {

		$this->assertMethodHasDocs(
			'Test_Class'
			, 'test_method_with_code_snippet'
			, array(
				'code_snippets' => array(
					array(
						'type' => 'php-code-snippet',
						'code' => "<?php\nrequire '/wordpress/wp-load.php';\necho docs_fixture_greeting();",
						'expected_output' => 'Hello from a method',
						'blueprint' => array(
							'steps' => array(
								array(
									'step' => 'writeFile',
									'path' => '/wordpress/wp-content/mu-plugins/docs-fixture.php',
									'data' => "<?php\nfunction docs_fixture_greeting() {\n\treturn 'Hello from a method';\n}\n",
								),
							),
						),
					),
				),
				'long_description' => '<p>Use this example:</p>',
			)
		);
	}

	/**
	 * Test that reusable setup Blueprints are exported once and referenced by snippets.
	 */
	public function test_method_reused_setup_blueprint() {

		$this->assertMethodHasDocs(
			'Test_Class'
			, 'test_method_with_reused_setup_blueprint'
			, array(
				'setup_blueprints' => array(
					'shared-greeting' => array(
						'steps' => array(
							array(
								'step' => 'writeFile',
								'path' => '/wordpress/wp-content/mu-plugins/shared-greeting.php',
								'data' => "<?php\nfunction docs_shared_greeting( \$name ) {\n\treturn \"Hello, \$name\";\n}\n",
							),
						),
					),
				),
				'code_snippets' => array(
					array(
						'type' => 'php-code-snippet',
						'code' => "<?php\nrequire '/wordpress/wp-load.php';\necho docs_shared_greeting( 'first' );",
						'expected_output' => 'Hello, first',
						'blueprint' => 'shared-greeting',
					),
					array(
						'type' => 'php-code-snippet',
						'code' => "<?php\nrequire '/wordpress/wp-load.php';\necho docs_shared_greeting( 'second' );",
						'expected_output' => 'Hello, second',
						'blueprint' => 'shared-greeting',
					),
				),
			)
		);
	}

	/**
	 * Test that methods can reference setup Blueprints from the file DocBlock.
	 */
	public function test_method_file_setup_blueprint() {

		$this->assertMethodHasDocs(
			'Test_Class'
			, 'test_method_with_file_setup_blueprint'
			, array(
				'setup_blueprints' => array(
					'file-greeting' => array(
						'steps' => array(
							array(
								'step' => 'writeFile',
								'path' => '/wordpress/wp-content/mu-plugins/file-greeting.php',
								'data' => "<?php\nfunction docs_file_greeting() {\n\treturn 'Hello from the file setup';\n}\n",
							),
						),
					),
				),
				'code_snippets' => array(
					array(
						'type' => 'php-code-snippet',
						'code' => "<?php\nrequire '/wordpress/wp-load.php';\necho docs_file_greeting();",
						'expected_output' => 'Hello from the file setup',
						'blueprint' => 'file-greeting',
					),
				),
				'long_description' => '',
			)
		);
	}

	/**
	 * Test tricky Markdown-like code fence parsing rules.
	 */
	public function test_code_snippet_fence_parser_edge_cases() {

		$description = implode(
			"\n",
			array(
				'Inline ```php is not a fence.',
				'',
				'``',
				'Two backticks are too short.',
				'``',
				'',
				'````php title="outer.php"',
				'<?php',
				'echo "outer";',
				'```',
				'echo "the smaller fence stays inside the larger fence";',
				'```',
				'````',
				'````expected-output',
				'outer',
				'````',
				'',
				'```expected-output',
				'This expected output appears before PHP and is ignored.',
				'```',
				'',
				'```php',
				'<?php',
				'echo "a different-length fence does not close";',
				'````',
				'echo "still inside";',
				'``` not a closer',
				'echo "still inside after text on the would-be closer";',
				'```',
				'```output',
				'different-length',
				'```',
				'',
				'    ```php',
				'    <?php',
				'      echo "indented fences strip the fence indentation";',
				'    ```',
				'    ```expected-output',
				'    indented',
				'    ```',
				'',
				'   ```php',
				'<?php',
				'echo "three leading spaces are still a fence";',
				'   ```',
				'   ```text/expected-output',
				'three leading spaces',
				'   ```',
				'',
				'```js',
				'console.log("another snippet");',
				'```',
				'```expected-output',
				'This belongs to no PHP snippet because the JS fence breaks the metadata run.',
				'```',
				'',
				'```blueprint',
				'{"steps":[{"step":"writeFile","path":"/tmp/ignored.php","data":"ignored"}]}',
				'```',
				'```js',
				'console.log("this fence breaks the pending blueprint");',
				'```',
				'```php',
				'<?php',
				'echo "no blueprint from before JS";',
				'```',
				'```expected-output',
				'no blueprint from before JS',
				'```',
				'',
				'```json blueprint',
				'{"steps":[{"step":"writeFile","path":"/tmp/one.php","data":"<?php echo 1;"}]}',
				'```',
				'```php',
				'<?php',
				'echo "blueprint before";',
				'```',
				'```expected_output',
				'blueprint before',
				'```',
				'',
				'```php',
				'<?php',
				'echo "blueprint after";',
				'```',
				'```blueprint',
				'{"steps":[{"step":"writeFile","path":"/tmp/two.php","data":"<?php echo 2;"}]}',
				'```',
				'```expected-output',
				'blueprint after',
				'```',
				'',
				'````php',
				'<?php',
				'echo "unterminated snippets are ignored";',
				'```js',
				'console.log("do not parse fences inside an unterminated fence");',
				'```',
			)
		);

		$this->assertEquals(
			array(
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\necho \"outer\";\n```\necho \"the smaller fence stays inside the larger fence\";\n```",
					'expected_output' => 'outer',
				),
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\necho \"a different-length fence does not close\";\n````\necho \"still inside\";\n``` not a closer\necho \"still inside after text on the would-be closer\";",
					'expected_output' => 'different-length',
				),
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\n  echo \"indented fences strip the fence indentation\";",
					'expected_output' => 'indented',
				),
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\necho \"three leading spaces are still a fence\";",
					'expected_output' => 'three leading spaces',
				),
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\necho \"no blueprint from before JS\";",
					'expected_output' => 'no blueprint from before JS',
				),
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\necho \"blueprint before\";",
					'expected_output' => 'blueprint before',
					'blueprint' => array(
						'steps' => array(
							array(
								'step' => 'writeFile',
								'path' => '/tmp/one.php',
								'data' => '<?php echo 1;',
							),
						),
					),
				),
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\necho \"blueprint after\";",
					'expected_output' => 'blueprint after',
					'blueprint' => array(
						'steps' => array(
							array(
								'step' => 'writeFile',
								'path' => '/tmp/two.php',
								'data' => '<?php echo 2;',
							),
						),
					),
				),
			),
			\WP_Parser\export_docblock_code_snippets( $description )
		);
	}

	/**
	 * Test that PHP snippets can omit optional metadata.
	 */
	public function test_code_snippet_without_metadata() {

		$this->assertEquals(
			array(
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\necho 'No metadata';",
				),
			),
			\WP_Parser\export_docblock_code_snippets(
				implode(
					"\n",
					array(
						'```php',
						'<?php',
						'echo \'No metadata\';',
						'```',
						'',
						'```js',
						'console.log("not exported");',
						'```',
					)
				)
			)
		);
	}

	/**
	 * Test that fence names are matched case-insensitively and ignore extra info.
	 */
	public function test_code_snippet_fence_info_strings() {

		$this->assertEquals(
			array(
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\necho docs_case_fixture();",
					'expected_output' => 'case fixture',
					'blueprint' => array(
						'steps' => array(
							array(
								'step' => 'writeFile',
								'path' => '/tmp/case.php',
								'data' => '<?php function docs_case_fixture() { return "case fixture"; }',
							),
						),
					),
				),
			),
			\WP_Parser\export_docblock_code_snippets(
				implode(
					"\n",
					array(
						'```JSON blueprint',
						'{"steps":[{"step":"writeFile","path":"/tmp/case.php","data":"<?php function docs_case_fixture() { return \"case fixture\"; }"}]}',
						'```',
						'```PHP editable=false',
						'<?php',
						'echo docs_case_fixture();',
						'```',
						'```Expected-Output copied from a run',
						'case fixture',
						'```',
					)
				)
			)
		);
	}

	/**
	 * Test that non-JSON Blueprint fences are preserved as strings.
	 */
	public function test_code_snippet_string_blueprint() {

		$this->assertEquals(
			array(
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\necho 'String blueprint';",
					'blueprint' => 'not-json',
				),
			),
			\WP_Parser\export_docblock_code_snippets(
				implode(
					"\n",
					array(
						'```blueprint',
						'not-json',
						'```',
						'```php',
						'<?php',
						'echo \'String blueprint\';',
						'```',
					)
				)
			)
		);
	}

	/**
	 * Test that metadata after expected output belongs to the next snippet.
	 */
	public function test_code_snippet_metadata_boundaries() {

		$this->assertEquals(
			array(
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\necho 'First';",
					'expected_output' => 'First',
				),
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\necho 'Second';",
					'blueprint' => array(
						'steps' => array(
							array(
								'step' => 'writeFile',
								'path' => '/tmp/second.php',
								'data' => '<?php echo "second setup";',
							),
						),
					),
				),
			),
			\WP_Parser\export_docblock_code_snippets(
				implode(
					"\n",
					array(
						'```php',
						'<?php',
						'echo \'First\';',
						'```',
						'```expected-output',
						'First',
						'```',
						'```blueprint',
						'{"steps":[{"step":"writeFile","path":"/tmp/second.php","data":"<?php echo \"second setup\";"}]}',
						'```',
						'```php',
						'<?php',
						'echo \'Second\';',
						'```',
					)
				)
			)
		);
	}

	/**
	 * Test named setup Blueprint definitions and references.
	 */
	public function test_code_snippet_named_setup_blueprints() {

		$setup_blueprints = array();
		$snippets         = \WP_Parser\export_docblock_code_snippets(
			implode(
				"\n",
				array(
					'```setup-blueprint shared',
					'{"steps":[{"step":"writeFile","path":"/tmp/shared.php","data":"<?php echo \"shared\";"}]}',
					'```',
					'```json setupblueprint json-shared',
					'{"steps":[{"step":"writeFile","path":"/tmp/json-shared.php","data":"<?php echo \"json shared\";"}]}',
					'```',
					'```blueprint',
					'{"steps":[{"step":"writeFile","path":"/tmp/ignored-inline.php","data":"ignored"}]}',
					'```',
					'```php blueprint=shared',
					'<?php',
					'echo "first";',
					'```',
					'```expected-output',
					'first',
					'```',
					'```php',
					'<?php',
					'echo "no leaked inline blueprint";',
					'```',
					'```expected-output',
					'no leaked inline blueprint',
					'```',
					'```php setupblueprint=json-shared',
					'<?php',
					'echo "second";',
					'```',
					'```expected-output',
					'second',
					'```',
					'```php setup-blueprint=shared',
					'<?php',
					'echo "third";',
					'```',
				)
			),
			$setup_blueprints
		);

		$this->assertEquals(
			array(
				'shared' => array(
					'steps' => array(
						array(
							'step' => 'writeFile',
							'path' => '/tmp/shared.php',
							'data' => '<?php echo "shared";',
						),
					),
				),
				'json-shared' => array(
					'steps' => array(
						array(
							'step' => 'writeFile',
							'path' => '/tmp/json-shared.php',
							'data' => '<?php echo "json shared";',
						),
					),
				),
			),
			$setup_blueprints
		);

		$this->assertEquals(
			array(
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\necho \"first\";",
					'expected_output' => 'first',
					'blueprint' => 'shared',
				),
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\necho \"no leaked inline blueprint\";",
					'expected_output' => 'no leaked inline blueprint',
				),
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\necho \"second\";",
					'expected_output' => 'second',
					'blueprint' => 'json-shared',
				),
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\necho \"third\";",
					'blueprint' => 'shared',
				),
			),
			$snippets
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
}
