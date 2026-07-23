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
				'setup_blueprints' => $this->file_greeting_setup_blueprints(),
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
				'setup_blueprints' => $this->file_greeting_setup_blueprints(),
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
	 * Test that special characters in documentation are preserved.
	 */
	public function test_special_characters_are_preserved() {
		$this->assertFunctionHasDocs(
			'test_special_characters',
			array(
				'long_description' => '<pre><code class="language-php">true === wp_is_valid_utf8( \'✏\' );' . "\n"
					. 'false === wp_is_valid_utf8( "just \\xC0 test" );</code></pre>',
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
				'setup_blueprints' => $this->file_greeting_setup_blueprints(),
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
	 * Test exact fence matching and indentation handling.
	 *
	 * @dataProvider code_snippet_fence_delimiters
	 */
	public function test_code_snippet_fence_delimiters( $description, $expected_code, $expected_output ) {

		$snippets = \WP_Parser\export_docblock_code_snippets( $description );

		$this->assertCount( 1, $snippets );
		$this->assertSame( $expected_code, $snippets[0]['code'] );
		$this->assertSame( $expected_output, $snippets[0]['expected_output'] );
	}

	public function code_snippet_fence_delimiters() {
		return array(
			'smaller runs stay inside a larger fence' => array(
				"````php interactive\n<?php\n```\necho 'inside';\n```\n````\n````expected-output\nouter\n````",
				"<?php\n```\necho 'inside';\n```",
				'outer',
			),
			'different runs and text do not close a fence' => array(
				"```php interactive\n<?php\n````\necho 'inside';\n``` not a closer\necho 'still inside';\n```\n```expected-output\nexact\n```",
				"<?php\n````\necho 'inside';\n``` not a closer\necho 'still inside';",
				'exact',
			),
			'arbitrary indentation is removed from content' => array(
				"    ```php interactive\n    <?php\n      echo 'indented';\n\t```\n    ```expected-output\n    indented\n```",
				"<?php\n  echo 'indented';",
				'indented',
			),
			'three leading spaces are accepted' => array(
				"   ```php interactive\n<?php echo 'three';\n```\n```expected-output\nthree\n   ```",
				"<?php echo 'three';",
				'three',
			),
		);
	}

	/**
	 * Test that inline, short, and unterminated fences cannot expose nested fences.
	 *
	 * @dataProvider ignored_code_fence_boundaries
	 */
	public function test_ignored_code_fence_boundaries( $description ) {

		$this->assertSame( array(), \WP_Parser\export_docblock_code_snippets( $description ) );
		$this->assertSame( $description, \WP_Parser\strip_docblock_code_snippet_fences( $description ) );
	}

	public function ignored_code_fence_boundaries() {
		return array(
			'inline backticks' => array( 'Inline ```php interactive is not a fence.' ),
			'two backticks' => array( "``php interactive\n<?php echo 'short';\n``" ),
			'unterminated fence' => array(
				"````php interactive\n<?php echo 'unterminated';\n```php interactive\n<?php echo 'nested';\n```",
			),
			'unterminated fence after a complete ordinary fence' => array(
				"```js\nconsole.log('complete');\n```\n\n````php interactive\n<?php echo 'unterminated';\n```",
			),
		);
	}

	/**
	 * Test inline setup Blueprints on either side of an interactive fence.
	 *
	 * @dataProvider inline_setup_blueprint_positions
	 */
	public function test_inline_setup_blueprint_positions( $description, $expected_path ) {

		$snippets = \WP_Parser\export_docblock_code_snippets( $description );

		$this->assertSame( $expected_path, $snippets[0]['blueprint']['steps'][0]['path'] );
	}

	public function inline_setup_blueprint_positions() {
		$php = "```php interactive\n<?php echo 'snippet';\n```";

		return array(
			'before PHP' => array(
				"```setup-blueprint\n{\"steps\":[{\"step\":\"writeFile\",\"path\":\"/tmp/before.php\",\"data\":\"\"}]}\n```\n" . $php,
				'/tmp/before.php',
			),
			'after PHP' => array(
				$php . "\n```setup-blueprint\n{\"steps\":[{\"step\":\"writeFile\",\"path\":\"/tmp/after.php\",\"data\":\"\"}]}\n```",
				'/tmp/after.php',
			),
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
	 * Test that unsupported info strings remain ordinary documentation.
	 *
	 * @dataProvider unrecognized_code_fence_info_strings
	 */
	public function test_unrecognized_code_fence_info_strings( $info, $body ) {

		$description = '```' . $info . "\n" . $body . "\n```";

		$this->assertSame( array(), \WP_Parser\export_docblock_code_snippets( $description ) );
		$this->assertSame( $description, \WP_Parser\strip_docblock_code_snippet_fences( $description ) );
	}

	public function unrecognized_code_fence_info_strings() {
		$php       = '<?php echo 1;';
		$blueprint = '{"steps":[]}';

		return array(
			'plain PHP' => array( 'php', $php ),
			'interactive inside an option' => array( 'php title="interactive-is-not-the-first-argument"', $php ),
			'combined language name' => array( 'php-interactive', $php ),
			'option on non-interactive PHP' => array( 'php example setup-blueprint=NOT-VALID', $php ),
			'uppercase interactive marker' => array( 'php INTERACTIVE setup-blueprint=ALSO-INVALID', $php ),
			'Blueprint alias' => array( 'blueprint', $blueprint ),
			'collapsed setup Blueprint name' => array( 'setupblueprint shared', $blueprint ),
			'setup Blueprint after JSON language' => array( 'json setup-blueprint shared', $blueprint ),
			'Blueprint reference alias' => array( 'php interactive blueprint=shared', $php ),
			'collapsed Blueprint reference' => array( 'php interactive setupblueprint=shared', $php ),
			'unsupported interactive option' => array( 'php interactive editable=false', $php ),
			'output alias' => array( 'output', 'output' ),
			'underscored expected output' => array( 'expected_output', 'output' ),
			'typed expected output' => array( 'text/expected-output', 'output' ),
			'uppercase PHP' => array( 'PHP interactive', $php ),
			'mixed-case PHP' => array( 'Php interactive', $php ),
			'uppercase interactive marker without options' => array( 'php INTERACTIVE', $php ),
			'mixed-case interactive marker' => array( 'php Interactive', $php ),
			'uppercase setup option' => array( 'php interactive SETUP-BLUEPRINT=shared', $php ),
			'uppercase setup language' => array( 'SETUP-BLUEPRINT shared', $blueprint ),
			'mixed-case setup language' => array( 'Setup-Blueprint shared', $blueprint ),
			'uppercase expected output' => array( 'EXPECTED-OUTPUT', 'output' ),
			'mixed-case expected output' => array( 'Expected-Output', 'output' ),
		);
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
		$first  = strpos( $stripped, '<!-- wp-parser-code-snippet-placeholder:0 -->' );
		$second = strpos( $stripped, '<!-- wp-parser-code-snippet-placeholder:1 -->' );
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

		$this->assertStringContainsString( $indent . '<!-- wp-parser-code-snippet-placeholder:0 -->', $stripped );
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

		$this->assertStringContainsString( $indent . '<!-- wp-parser-code-snippet-placeholder:0 -->', $stripped );
		$this->assertSame(
			'<p>Before</p> <!-- wp-parser-code-snippet:0 --> <p>After</p>',
			\WP_Parser\format_long_description( $stripped )
		);
	}

	/**
	 * Test adjacent, deeply indented placeholders do not merge into visible code.
	 */
	public function test_adjacent_indented_code_snippet_placeholders_remain_html() {

		$description = implode(
			"\n",
			array(
				'Before',
				'',
				'    ```php interactive',
				'    <?php echo 1;',
				'    ```',
				'',
				'    ```expected-output',
				'    1',
				'    ```',
				'',
				'    ```php interactive',
				'    <?php echo 2;',
				'    ```',
				'',
				'    ```expected-output',
				'    2',
				'    ```',
				'',
				'After',
			)
		);
		$fences  = \WP_Parser\get_docblock_code_fences( $description );
		$stripped = \WP_Parser\strip_docblock_code_snippet_fences( $description, $fences );

		$this->assertSame(
			'<p>Before</p> <!-- wp-parser-code-snippet:0 --> <!-- wp-parser-code-snippet:1 --> <p>After</p>',
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
	 * Test that author text cannot collide with generated snippet placeholders.
	 *
	 * @dataProvider reserved_code_snippet_placeholders
	 */
	public function test_reserved_code_snippet_placeholder_fails( $source ) {

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'is reserved for generated snippet placement' );

		\WP_Parser\get_docblock_code_fences(
			"Before\n\n" . $source . "\n\n```php interactive\n<?php echo 1;\n```"
		);
	}

	/**
	 * Returns collision-prone placements of the reserved placeholder comments.
	 */
	public function reserved_code_snippet_placeholders() {
		return array(
			'public placeholder' => array( '<!-- wp-parser-code-snippet:0 -->' ),
			'indented public placeholder' => array( '    <!-- wp-parser-code-snippet:0 -->' ),
			'intermediate placeholder' => array( '<!-- wp-parser-code-snippet-placeholder:0 -->' ),
			'intermediate placeholder in an ordinary fence' => array(
				"```\n<!-- wp-parser-code-snippet-placeholder:0 -->\n```",
			),
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
				'setup_blueprints' => $this->file_greeting_setup_blueprints(),
			)
		);
	}

	private function file_greeting_setup_blueprints() {
		return array(
			'file-greeting' => array(
				'steps' => array(
					array(
						'step' => 'writeFile',
						'path' => '/wordpress/wp-content/mu-plugins/file-greeting.php',
						'data' => "<?php\nfunction docs_file_greeting() {\n\treturn 'Hello from the file setup';\n}\n",
					),
				),
			),
		);
	}
}
