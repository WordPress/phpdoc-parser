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
	 * Test that raw HTML code retains phpDocumentor's block formatting.
	 */
	public function test_plain_html_code_formatting() {

		$this->assertEquals(
			"<pre><code>first\nsecond\n</code></pre>",
			\WP_Parser\format_long_description( "<code>\nfirst\nsecond\n</code>" )
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
			, array(
				'description' => 'A test action.',
				'long_description' => '<!-- wp-parser-code-snippet:0 -->',
				'code_snippets' => array(
					array(
						'type' => 'php-code-snippet',
						'code' => "<?php\nrequire '/wordpress/wp-load.php';\necho docs_file_greeting();",
						'expected_output' => 'Hello from the file setup',
						'blueprint' => 'file-greeting',
					),
				),
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
	 * Test fences that occupy the first paragraph of a DocBlock.
	 */
	public function test_fence_first_docblocks() {

		$file   = __DIR__ . '/fence-first-docblocks.inc';
		$parsed = \WP_Parser\parse_files( array( $file ), __DIR__ );
		$file   = $parsed[0];

		$this->assertSame( '', $file['file']['description'] );
		$this->assertSame( '<!-- wp-parser-code-snippet:0 -->', $file['file']['long_description'] );
		$this->assertEquals(
			array(
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\n" .
						"@unlink( '/tmp/phpdoc-parser-file-review' );\n" .
						"@! file_exists( '/tmp/phpdoc-parser-file-review' );\n" .
						"echo 'file fence';",
					'blueprint' => 'shared',
				),
			),
			$file['file']['code_snippets']
		);
		$this->assertEquals(
			array(
				'shared' => array( 'steps' => array() ),
			),
			$file['file']['setup_blueprints']
		);

		$this->assertSame( '', $file['functions'][0]['doc']['description'] );
		$this->assertSame( '<!-- wp-parser-code-snippet:0 -->', $file['functions'][0]['doc']['long_description'] );
		$this->assertEquals(
			array(
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\n\necho 'fence first';",
					'blueprint' => 'shared',
				),
			),
			$file['functions'][0]['doc']['code_snippets']
		);

		$this->assertSame(
			"<?php\n// This source line ends in a period.\necho 'period split';",
			$file['functions'][1]['doc']['code_snippets'][0]['code']
		);
		$this->assertSame(
			'<!-- wp-parser-code-snippet:0 -->',
			$file['functions'][1]['doc']['long_description']
		);

		$this->assertEquals(
			array(
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\n" .
						"@_before( 'not parsed before a letter-named tag' );\n" .
						"@unlink( '/tmp/phpdoc-parser-review' );\n" .
						"@! file_exists( '/tmp/phpdoc-parser-review' );\n" .
						"@\$suppressed_value;\n" .
						"@( file_exists( '/tmp/phpdoc-parser-review' ) );\n" .
						"@_same( 'parsed after the tag block starts' );\n" .
						"@2inside( 'numeric tag name parsed after the tag block starts' );\n" .
						"  @author( 'indentation makes this part of the preceding tag' );\n" .
						"@since( 'inside-fence' );\n" .
						"echo 'at sign';",
					'expected_output' => 'at sign',
				),
			),
			$file['functions'][2]['doc']['code_snippets']
		);
		$this->assertEquals(
			array(
				array(
					'name' => 'since',
					'content' => '1.0.0',
				),
				array(
					'name' => '_before',
					'content' => 'A real custom tag.',
				),
				array(
					'name' => '_same',
					'content' => 'Another real custom tag.',
				),
				array(
					'name' => 'author',
					'content' => 'Jane Doe',
				),
			),
			$file['functions'][2]['doc']['tags']
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
				'long_description' => '<p>Use this example:</p> <!-- wp-parser-code-snippet:0 -->',
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
				'long_description' => '<!-- wp-parser-code-snippet:0 -->',
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
				'````php interactive',
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
				'```php interactive',
				'<?php',
				'echo "a different-length fence does not close";',
				'````',
				'echo "still inside";',
				'``` not a closer',
				'echo "still inside after text on the would-be closer";',
				'```',
				'```expected-output',
				'different-length',
				'```',
				'',
				'    ```php interactive',
				'    <?php',
				'      echo "indented fences strip the fence indentation";',
				'    ```',
				'    ```expected-output',
				'    indented',
				'    ```',
				'',
				'   ```php interactive',
				'<?php',
				'echo "three leading spaces are still a fence";',
				'   ```',
				'   ```expected-output',
				'three leading spaces',
				'   ```',
				'',
				'```js',
				'console.log("another snippet");',
				'```',
				'```js',
				'console.log("this fence breaks the pending blueprint");',
				'```',
				'```php interactive',
				'<?php',
				'echo "no blueprint from before JS";',
				'```',
				'```expected-output',
				'no blueprint from before JS',
				'```',
				'',
				'```setup-blueprint',
				'{"steps":[{"step":"writeFile","path":"/tmp/one.php","data":"<?php echo 1;"}]}',
				'```',
				'```php interactive',
				'<?php',
				'echo "blueprint before";',
				'```',
				'```expected-output',
				'blueprint before',
				'```',
				'',
				'```php interactive',
				'<?php',
				'echo "blueprint after";',
				'```',
				'```setup-blueprint',
				'{"steps":[{"step":"writeFile","path":"/tmp/two.php","data":"<?php echo 2;"}]}',
				'```',
				'```expected-output',
				'blueprint after',
				'```',
				'',
				'````php interactive',
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
	 * Test that large valid fences do not depend on the PCRE JIT stack size.
	 */
	public function test_large_code_snippet_fence() {

		$line_count  = 12000;
		$description = "```php interactive\n" . str_repeat( "echo 'line';\n", $line_count ) . '```';
		$fences      = \WP_Parser\get_docblock_code_fences( $description );

		$this->assertCount( 1, $fences );
		$this->assertEquals( $line_count, substr_count( $fences[0]['code'], "echo 'line';" ) );
		$this->assertTrue( $fences[0]['is_interactive_php'] );
	}

	/**
	 * Test that trailing blank lines are not included in exported code.
	 */
	public function test_code_snippet_trims_trailing_blank_lines() {

		$fences = \WP_Parser\get_docblock_code_fences(
			"```php interactive\n<?php echo 'trimmed';\n\n\n```"
		);

		$this->assertEquals( "<?php echo 'trimmed';", $fences[0]['code'] );
	}

	/**
	 * Test that content lines do not have to repeat their fence's indentation.
	 */
	public function test_code_snippet_content_indentation_is_optional() {

		$fences = \WP_Parser\get_docblock_code_fences(
			"      ```php interactive\n" .
			"<?php\n" .
			"        echo 'spaces';\n" .
			"   echo 'partially indented';\n" .
			"\techo 'tab';\n" .
			"      ```"
		);

		$this->assertEquals(
			"<?php\n  echo 'spaces';\necho 'partially indented';\n\techo 'tab';",
			$fences[0]['code']
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
						'```php interactive',
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
	 * Test that PHP fences are ordinary documentation unless marked interactive.
	 */
	public function test_plain_php_fences_are_not_code_snippets() {

		$description = implode(
			"\n",
			array(
				'```php',
				'<?php echo "plain";',
				'```',
				'```php title="interactive-is-not-the-first-argument"',
				'<?php echo "also plain";',
				'```',
				'```php-interactive',
				'<?php echo "still plain";',
				'```',
				'```php example setup-blueprint=NOT-VALID',
				'<?php echo "setup-looking metadata on a plain fence";',
				'```',
				'```php INTERACTIVE setup-blueprint=ALSO-INVALID',
				'<?php echo "the interactive marker is case-sensitive";',
				'```',
			)
		);

		$this->assertEquals( array(), \WP_Parser\export_docblock_code_snippets( $description ) );
		$this->assertEquals( $description, \WP_Parser\strip_docblock_code_snippet_fences( $description ) );
	}

	/**
	 * Test that each PHP fence is replaced with an inline placeholder, in order,
	 * so the theme can render each snippet between the surrounding prose instead
	 * of collapsing every snippet to the end of the description. Snippet-metadata
	 * fences (expected-output, Blueprints) are removed.
	 */
	public function test_code_snippet_inline_placeholders() {

		$description = implode(
			"\n",
			array(
				'First prose.',
				'',
				'```php interactive',
				'<?php',
				'echo step_one();',
				'```',
				'',
				'Middle prose.',
				'',
				'```php interactive',
				'<?php',
				'echo step_two();',
				'```',
				'',
				'```expected-output',
				'done',
				'```',
				'',
				'Closing prose.',
			)
		);

		$stripped = \WP_Parser\strip_docblock_code_snippet_fences( $description );

		// One placeholder per PHP fence, in document order, with the prose around them.
		$first  = strpos( $stripped, '<!-- wp-parser-code-snippet:0 -->' );
		$second = strpos( $stripped, '<!-- wp-parser-code-snippet:1 -->' );
		$this->assertNotFalse( $first );
		$this->assertNotFalse( $second );
		$this->assertLessThan( $second, $first );
		$this->assertLessThan( $first, strpos( $stripped, 'First prose.' ) );
		$this->assertGreaterThan( $first, strpos( $stripped, 'Middle prose.' ) );
		$this->assertGreaterThan( $second, strpos( $stripped, 'Closing prose.' ) );

		// No raw PHP fence or metadata fence is left behind in the description.
		$this->assertStringNotContainsString( '```', $stripped );
		$this->assertStringNotContainsString( '<?php', $stripped );

		// Placeholder indices align with the exported snippets.
		$this->assertEquals(
			array(
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\necho step_one();",
				),
				array(
					'type'            => 'php-code-snippet',
					'code'            => "<?php\necho step_two();",
					'expected_output' => 'done',
				),
			),
			\WP_Parser\export_docblock_code_snippets( $description )
		);
	}

	/**
	 * Test that a snippet placeholder remains inside its Markdown list item.
	 *
	 * @dataProvider markdown_list_code_snippet_indentation
	 */
	public function test_indented_code_snippet_placeholder_preserves_markdown_list( $marker, $indent, $expected ) {

		$description = implode(
			"\n",
			array(
				$marker . ' Before',
				'',
				$indent . '```php interactive',
				$indent . '<?php echo 1;',
				$indent . '```',
				'',
				$indent . 'After',
			)
		);
		$stripped = \WP_Parser\strip_docblock_code_snippet_fences( $description );

		$this->assertStringContainsString( $indent . '<!-- wp-parser-code-snippet:0 -->', $stripped );
		$this->assertSame(
			$expected,
			\WP_Parser\format_long_description( $stripped )
		);
	}

	/**
	 * Returns Markdown list markers and their content indentation.
	 */
	public function markdown_list_code_snippet_indentation() {
		return array(
			'one-digit ordered marker' => array(
				'1.',
				'   ',
				'<ol> <li> <p>Before</p> <!-- wp-parser-code-snippet:0 --> <p>After</p> </li> </ol>',
			),
			'three-digit ordered marker' => array(
				'100.',
				'     ',
				'<ol start="100"> <li> <p>Before</p> <!-- wp-parser-code-snippet:0 --> <p>After</p> </li> </ol>',
			),
			'unordered marker' => array(
				'-',
				'  ',
				'<ul> <li> <p>Before</p> <!-- wp-parser-code-snippet:0 --> <p>After</p> </li> </ul>',
			),
		);
	}

	/**
	 * Test that arbitrary fence indentation does not turn a placeholder into code.
	 *
	 * @dataProvider standalone_code_snippet_indentation
	 */
	public function test_standalone_indented_code_snippet_placeholder_remains_html( $indent ) {

		$description = "Before\n\n" .
			$indent . "```php interactive\n" .
			$indent . "<?php echo 1;\n" .
			$indent . "```\n\nAfter";
		$stripped = \WP_Parser\strip_docblock_code_snippet_fences( $description );

		$this->assertStringContainsString( $indent . '<!-- wp-parser-code-snippet:0 -->', $stripped );
		$this->assertSame(
			'<p>Before</p> <!-- wp-parser-code-snippet:0 --> <p>After</p>',
			\WP_Parser\format_long_description( $stripped )
		);
	}

	/**
	 * Returns indentation that Markdown otherwise treats as a code block.
	 */
	public function standalone_code_snippet_indentation() {
		return array(
			'four spaces' => array( '    ' ),
			'eight spaces' => array( '        ' ),
			'tab' => array( "\t" ),
			'two tabs' => array( "\t\t" ),
		);
	}

	/**
	 * Test that snippet metadata fences do not accept extra arguments.
	 */
	public function test_code_snippet_metadata_rejects_extra_arguments() {

		$this->assertEquals(
			array(
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\necho docs_case_fixture();",
				),
			),
			\WP_Parser\export_docblock_code_snippets(
				implode(
					"\n",
					array(
						'```setup-blueprint copied-from-another-example extra-argument',
						'{"steps":[]}',
						'```',
						'```php interactive',
						'<?php',
						'echo docs_case_fixture();',
						'```',
						'```expected-output copied from a run',
						'case fixture',
						'```',
					)
				)
			)
		);
	}

	/**
	 * Test that obsolete snippet syntax is treated as ordinary documentation.
	 */
	public function test_code_snippet_syntax_aliases_are_not_recognized() {

		$description = implode(
			"\n",
			array(
				'```blueprint',
				'{"steps":[]}',
				'```',
				'```setupblueprint shared',
				'{"steps":[]}',
				'```',
				'```json setup-blueprint shared',
				'{"steps":[]}',
				'```',
				'```php interactive blueprint=shared',
				'<?php echo "blueprint alias";',
				'```',
				'```php interactive setupblueprint=shared',
				'<?php echo "setupblueprint alias";',
				'```',
				'```php interactive editable=false',
				'<?php echo "unsupported extra argument";',
				'```',
				'```output',
				'output alias',
				'```',
				'```expected_output',
				'expected output alias',
				'```',
				'```text/expected-output',
				'text expected output alias',
				'```',
			)
		);

		$this->assertEquals( array(), \WP_Parser\export_docblock_code_snippets( $description ) );
		$this->assertEquals( $description, \WP_Parser\strip_docblock_code_snippet_fences( $description ) );
	}

	/**
	 * Test that capitalization variants are treated as ordinary documentation.
	 */
	public function test_code_snippet_syntax_is_case_sensitive() {

		$description = implode(
			"\n",
			array(
				'```PHP interactive',
				'<?php echo "uppercase language";',
				'```',
				'```Php interactive',
				'<?php echo "mixed-case language";',
				'```',
				'```php INTERACTIVE',
				'<?php echo "uppercase marker";',
				'```',
				'```php Interactive',
				'<?php echo "mixed-case marker";',
				'```',
				'```php interactive SETUP-BLUEPRINT=shared',
				'<?php echo "uppercase option";',
				'```',
				'```SETUP-BLUEPRINT shared',
				'{"steps":[]}',
				'```',
				'```Setup-Blueprint shared',
				'{"steps":[]}',
				'```',
				'```EXPECTED-OUTPUT',
				'uppercase output',
				'```',
				'```Expected-Output',
				'mixed-case output',
				'```',
			)
		);

		$this->assertEquals( array(), \WP_Parser\export_docblock_code_snippets( $description ) );
		$this->assertEquals( $description, \WP_Parser\strip_docblock_code_snippet_fences( $description ) );
	}

	/**
	 * Test that invalid setup Blueprint JSON stops snippet export.
	 *
	 * @dataProvider invalid_setup_blueprints
	 */
	public function test_invalid_setup_blueprint_json_fails( $fence_info, $blueprint ) {

		$this->expectException( \InvalidArgumentException::class );

		\WP_Parser\export_docblock_code_snippets(
			implode(
				"\n",
				array(
					'```' . $fence_info,
					$blueprint,
					'```',
					'```php interactive',
					'<?php echo "unreachable";',
					'```',
				)
			)
		);
	}

	/**
	 * Returns malformed JSON and valid JSON values that are not Blueprint objects.
	 */
	public function invalid_setup_blueprints() {

		return array(
			'malformed inline Blueprint' => array( 'setup-blueprint', '{"steps":' ),
			'malformed named Blueprint' => array( 'setup-blueprint shared', '{"steps":' ),
			'plain text' => array( 'setup-blueprint', 'not-json' ),
			'JSON null' => array( 'setup-blueprint', 'null' ),
			'JSON string' => array( 'setup-blueprint', '"string"' ),
			'JSON number' => array( 'setup-blueprint', '42' ),
			'JSON list' => array( 'setup-blueprint', '[]' ),
			'trailing content' => array( 'setup-blueprint', '{"steps":[]} trailing' ),
		);
	}

	/**
	 * Test that Blueprint objects retain their JSON type through export and import decoding.
	 *
	 * @dataProvider blueprint_object_shapes
	 */
	public function test_blueprint_json_object_shapes_are_preserved( $blueprint ) {

		$decoded  = \WP_Parser\decode_docblock_blueprint( $blueprint );
		$exported = json_encode( $decoded );
		$imported = json_decode( $exported );
		\WP_Parser\preserve_json_object_shapes( $imported );

		$this->assertSame( $blueprint, $exported );
		$this->assertSame( $blueprint, json_encode( $imported ) );
	}

	/**
	 * Returns Blueprint objects whose shape associative decoding would otherwise lose.
	 */
	public function blueprint_object_shapes() {

		return array(
			'empty Blueprint' => array( '{}' ),
			'nested empty object' => array( '{"constants":{},"steps":[]}' ),
			'numeric object keys' => array( '{"siteOptions":{"0":"zero","1":"one"},"steps":[]}' ),
		);
	}

	/**
	 * Test that reusable setup Blueprint names use one unambiguous form.
	 *
	 * @dataProvider invalid_setup_blueprint_names
	 */
	public function test_invalid_setup_blueprint_name_fails( $fence_info, $contents ) {

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'must be lowercase kebab-case starting with a letter' );

		\WP_Parser\export_docblock_code_snippets(
			"```" . $fence_info . "\n" . $contents . "\n```"
		);
	}

	/**
	 * Returns malformed reusable setup Blueprint definitions and references.
	 */
	public function invalid_setup_blueprint_names() {

		return array(
			'numeric definition' => array( 'setup-blueprint 0', '{}' ),
			'uppercase definition' => array( 'setup-blueprint Shared', '{}' ),
			'underscore definition' => array( 'setup-blueprint shared_name', '{}' ),
			'leading hyphen definition' => array( 'setup-blueprint -shared', '{}' ),
			'trailing hyphen definition' => array( 'setup-blueprint shared-', '{}' ),
			'repeated hyphen definition' => array( 'setup-blueprint shared--name', '{}' ),
			'dotted definition' => array( 'setup-blueprint shared.name', '{}' ),
			'numeric reference' => array( 'php interactive setup-blueprint=0', '<?php echo 1;' ),
			'uppercase reference' => array( 'php interactive setup-blueprint=Shared', '<?php echo 1;' ),
			'underscore reference' => array( 'php interactive setup-blueprint=shared_name', '<?php echo 1;' ),
			'empty reference' => array( 'php interactive setup-blueprint=', '<?php echo 1;' ),
		);
	}

	/**
	 * Test valid reusable setup Blueprint names at the grammar boundaries.
	 *
	 * @dataProvider valid_setup_blueprint_names
	 */
	public function test_valid_setup_blueprint_name( $name ) {

		$setup_blueprints = array();
		$snippets         = \WP_Parser\export_docblock_code_snippets(
			"```setup-blueprint " . $name . "\n{}\n```\n" .
			"```php interactive setup-blueprint=" . $name . "\n<?php echo 1;\n```",
			$setup_blueprints
		);

		$this->assertArrayHasKey( $name, $setup_blueprints );
		$this->assertSame( $name, $snippets[0]['blueprint'] );
	}

	/**
	 * Returns valid reusable setup Blueprint names.
	 */
	public function valid_setup_blueprint_names() {

		return array(
			'single letter' => array( 'a' ),
			'trailing number' => array( 'shared0' ),
			'hyphenated number' => array( 'shared-0' ),
		);
	}

	/**
	 * Test that duplicate reusable setup Blueprint definitions fail instead of overwriting.
	 */
	public function test_duplicate_setup_blueprint_name_fails() {

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Setup Blueprint "shared" is defined more than once on lines 1 and 4 of the long description.' );

		\WP_Parser\export_docblock_code_snippets(
			implode(
				"\n",
				array(
					'```setup-blueprint shared',
					'{"steps":[]}',
					'```',
					'```setup-blueprint shared',
					'{"constants":{}}',
					'```',
				)
			)
		);
	}

	/**
	 * Test that a local setup Blueprint cannot silently replace an inherited definition.
	 */
	public function test_setup_blueprint_name_cannot_shadow_inherited_definition() {

		$file = __DIR__ . '/shadowed-blueprint.inc';

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			'DocBlock for class "Shadowed_Blueprint_Example" in shadowed-blueprint.inc starting on source line 11: ' .
			'Setup Blueprint "shared" on line 1 of the long description is already defined in an enclosing DocBlock.'
		);

		\WP_Parser\parse_files( array( $file ), __DIR__ );
	}

	/**
	 * Test that invalid Blueprint failures identify the definition location.
	 */
	public function test_invalid_setup_blueprint_error_identifies_fence() {

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Setup Blueprint "shared" on line 2 of the long description must contain valid JSON' );

		\WP_Parser\export_docblock_code_snippets(
			implode(
				"\n",
				array(
					'Introductory prose.',
					'```setup-blueprint shared',
					'{"steps":',
					'```',
				)
			)
		);
	}

	/**
	 * Test that one snippet cannot silently choose between multiple setup Blueprints.
	 *
	 * @dataProvider ambiguous_setup_blueprints
	 */
	public function test_ambiguous_setup_blueprints_fail( $description ) {

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'more than one setup Blueprint' );

		\WP_Parser\export_docblock_code_snippets( $description );
	}

	public function ambiguous_setup_blueprints() {

		$inline = "```setup-blueprint\n{}\n```";
		$named  = "```php interactive setup-blueprint=shared\n<?php echo 1;\n```";
		$plain  = "```php interactive\n<?php echo 1;\n```";

		return array(
			'inline before named reference' => array( $inline . "\n" . $named ),
			'inline after named reference' => array( $named . "\n" . $inline ),
			'two inline Blueprints before PHP' => array( $inline . "\n" . $inline . "\n" . $plain ),
			'two inline Blueprints after PHP' => array( $plain . "\n" . $inline . "\n" . $inline ),
		);
	}

	/**
	 * Test that Blueprint failures identify their source file and entity.
	 *
	 * @dataProvider invalid_blueprint_source_files
	 */
	public function test_blueprint_error_identifies_source( $fixture, $entity, $error ) {

		$file = __DIR__ . '/' . $fixture;

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'DocBlock for function "' . $entity . '" in ' . $fixture . ' starting on source line 3: ' . $error );

		\WP_Parser\parse_files( array( $file ), __DIR__ );
	}

	/**
	 * Returns malformed and unresolved Blueprint fixture errors.
	 */
	public function invalid_blueprint_source_files() {

		return array(
			'invalid JSON' => array(
				'invalid-blueprint.inc',
				'invalid_blueprint_example',
				'Setup Blueprint "broken" on line 1 of the long description must contain valid JSON',
			),
			'undefined reference' => array(
				'undefined-blueprint.inc',
				'undefined_blueprint_example',
				'Setup Blueprint "missing" referenced on line 1 of the long description is not defined.',
			),
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
						'```php interactive',
						'<?php',
						'echo \'First\';',
						'```',
						'```expected-output',
						'First',
						'```',
						'```setup-blueprint',
						'{"steps":[{"step":"writeFile","path":"/tmp/second.php","data":"<?php echo \"second setup\";"}]}',
						'```',
						'```php interactive',
						'<?php',
						'echo \'Second\';',
						'```',
					)
				)
			)
		);
	}

	/**
	 * Test that recognized snippet metadata must belong to an interactive fence.
	 *
	 * @dataProvider unattached_snippet_metadata
	 */
	public function test_unattached_snippet_metadata_fails( $description ) {

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'is not attached to an interactive PHP fence' );

		\WP_Parser\export_docblock_code_snippets( $description );
	}

	public function unattached_snippet_metadata() {

		$php = "```php interactive\n<?php echo 1;\n```";

		return array(
			'expected output before PHP' => array( "```expected-output\n1\n```\n" . $php ),
			'expected output after prose' => array( $php . "\nProse.\n```expected-output\n1\n```" ),
			'inline Blueprint before prose' => array( "```setup-blueprint\n{}\n```\nProse.\n" . $php ),
			'inline Blueprint after prose' => array( $php . "\nProse.\n```setup-blueprint\n{}\n```" ),
			'duplicate expected output' => array( $php . "\n```expected-output\n1\n```\n```expected-output\n2\n```" ),
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
					'```php interactive setup-blueprint=shared',
					'<?php',
					'echo "first";',
					'```',
					'```expected-output',
					'first',
					'```',
					'```php interactive',
					'<?php',
					'echo "no leaked inline blueprint";',
					'```',
					'```expected-output',
					'no leaked inline blueprint',
					'```',
					'```php interactive setup-blueprint=shared',
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
			, array(
				'description' => 'This is a docblock for a class property.',
				'long_description' => '<!-- wp-parser-code-snippet:0 -->',
				'code_snippets' => array(
					array(
						'type' => 'php-code-snippet',
						'code' => "<?php\n// This property snippet line ends in a period.\n@unlink( '/tmp/phpdoc-parser-property' );\nrequire '/wordpress/wp-load.php';\necho docs_file_greeting();",
						'expected_output' => 'Hello from the file setup',
						'blueprint' => 'file-greeting',
					),
				),
				'tags' => array(
					array(
						'name' => 'since',
						'content' => '3.0.0',
					),
					array(
						'name' => 'var',
						'content' => '',
						'types' => array( 'string' ),
						'variable' => '',
					),
				),
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
}
