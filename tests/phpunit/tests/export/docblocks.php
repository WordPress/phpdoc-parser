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
	 * Test that whitespace inside a generic type does not end the type (wp_localize_script).
	 */
	public function test_generic_type_with_whitespace() {

		$this->assertFunctionHasDocs(
			'docs_wp_localize_script'
			, array(
				'tags' => array(
					array(
						'name' => 'param',
						'content' => 'Script handle the data will be attached to.',
						'types' => array( 'string' ),
						'variable' => '$handle',
					),
					array(
						'name' => 'param',
						'content' => 'Name for the JavaScript object. Passed directly, so it should be qualified JS variable.<br>                                         Example: \'/[a-zA-Z0-9_]+/\'.',
						'types' => array( 'string' ),
						'variable' => '$object_name',
					),
					array(
						'name' => 'param',
						'content' => 'The data itself. The data can be either a single or multi-dimensional array.',
						'types' => array( 'array<string, mixed>' ),
						'variable' => '$l10n',
					),
					array(
						'name' => 'return',
						'content' => 'True if the script was successfully localized, false otherwise.',
						'types' => array( 'bool' ),
					),
				),
			)
		);
	}

	/**
	 * Test that a union nested in a generic stays one type and class names inside it resolve (wp_ai_client_prompt).
	 */
	public function test_nested_union_inside_generic() {

		$this->assertFunctionHasDocs(
			'docs_wp_ai_client_prompt'
			, array(
				'tags' => array(
					array(
						'name' => 'param',
						'content' => 'Optional. Initial prompt content.<br>                                                                                                  A string for simple text prompts,                                                                                                   a MessagePart or Message object for                                                                                                   structured content, an array for a                                                                                                   message array shape, or a list of                                                                                                   parts or messages for multi-turn                                                                                                   conversations. Default null.',
						'types' => array(
							'string',
							'\\WordPress\\AiClient\\Messages\\DTO\\MessagePart',
							'\\WordPress\\AiClient\\Messages\\DTO\\Message',
							'array',
							'list<string|\\WordPress\\AiClient\\Messages\\DTO\\MessagePart|array>',
							'list<\\WordPress\\AiClient\\Messages\\DTO\\Message>',
							'null',
						),
						'variable' => '$prompt',
					),
					array(
						'name' => 'return',
						'content' => 'The prompt builder instance.',
						'types' => array( 'WP_AI_Client_Prompt_Builder' ),
					),
				),
			)
		);
	}

	/**
	 * Test type expressions in PHPStan's grammar: shapes, nullables, intersections, callables, pseudo-types, templates, constants and references.
	 */
	public function test_phpstan_type_expressions() {

		$this->assertFunctionHasDocs(
			'test_phpstan_types'
			, array(
				'tags' => array(
					array(
						'name' => 'template',
						'content' => 'T',
					),
					array(
						'name' => 'param',
						'content' => 'An array shape.',
						'types' => array( 'array{label: string, count?: int}' ),
						'variable' => '$shape',
					),
					array(
						'name' => 'param',
						'content' => 'A nullable class.',
						'types' => array( '?\\WP_Post' ),
						'variable' => '$post',
					),
					array(
						'name' => 'param',
						'content' => 'An intersection.',
						'types' => array( '\\WordPress\\AiClient\\Messages\\DTO\\MessagePart&\\Countable' ),
						'variable' => '$both',
					),
					array(
						'name' => 'param',
						'content' => 'Class names resolved inside generics.',
						'types' => array( 'iterable<int, list<\\WordPress\\AiClient\\Messages\\DTO\\MessagePart>>' ),
						'variable' => '$parts',
					),
					array(
						'name' => 'param',
						'content' => 'A callable with a signature.',
						'types' => array( 'callable(string $a): bool' ),
						'variable' => '$callback',
					),
					array(
						'name' => 'param',
						'content' => 'PHPStan pseudo-types.',
						'types' => array( 'non-empty-string', 'int<0, max>' ),
						'variable' => '$pseudo',
					),
					array(
						'name' => 'param',
						'content' => 'A template type.',
						'types' => array( 'class-string<T>' ),
						'variable' => '$class',
					),
					array(
						'name' => 'param',
						'content' => 'A class constant pattern.',
						'types' => array( '\\WordPress\\AiClient\\Messages\\DTO\\MessagePart::TYPE_*' ),
						'variable' => '$constant',
					),
					array(
						'name' => 'param',
						'content' => 'A reference.',
						'types' => array( 'array<int, string>' ),
						'variable' => '$by_ref',
					),
					array(
						'name' => 'param',
						'content' => 'No type.',
						'types' => array(),
						'variable' => '$untyped',
					),
					array(
						'name' => 'return',
						'content' => 'Labels keyed by name.',
						'types' => array( 'array<string, string>' ),
					),
				),
			)
		);
	}

	/**
	 * Test that an array shape written over several lines is one type, printed on one line.
	 */
	public function test_multiline_array_shape_return_type() {

		$this->assertFunctionHasDocs(
			'test_multiline_shape_return_type'
			, array(
				'tags' => array(
					array(
						'name' => 'return',
						'content' => '',
						'types' => array( 'array{ label: string, badge: array{ color: string, }, }', 'false' ),
					),
				),
			)
		);
	}

	/**
	 * Test that a tag PHPStan's parser rejects is exported as phpDocumentor reads it.
	 */
	public function test_invalid_type_keeps_phpdocumentor_reading() {

		$this->assertFunctionHasDocs(
			'test_invalid_type_fallback'
			, array(
				'tags' => array(
					array(
						'name' => 'param',
						'content' => 'The arrow-&gt;prop blah',
						'types' => array( '\\array<int' ),
						'variable' => '$x',
					),
					array(
						'name' => 'return',
						'content' => 'A generator of things&gt;',
						'types' => array( '\\Generator<int' ),
					),
				),
			)
		);
	}

	/**
	 * Test that a parameter whose description is a hash keeps its type (wp_register_ability).
	 */
	public function test_generic_type_before_hash() {

		$func = $this->find_entity_data_in( $this->export_data, 'functions', 'docs_wp_register_ability' );
		$args = $func['doc']['tags'][1];

		$this->assertSame( array( 'array<string, mixed>' ), $args['types'] );
		$this->assertSame( '$args', $args['variable'] );
		$this->assertStringStartsWith( '{     An associative array of arguments for configuring the ability.<br>    @type string ', $args['content'] );
	}

	/**
	 * Test that hash notation is exported as a structure beside the unchanged `content`.
	 *
	 * Covers nested hashes, `$0` and `...$0` names, an entry with no name, a
	 * description on continuation lines, a generic with whitespace, a `@type`
	 * PHPStan's parser rejects, a `},` closing line, and a named `@return` hash.
	 * Each `content` string is what the parser exported before hashes were read.
	 */
	public function test_hash_notation() {

		$this->assertFunctionHasDocs(
			'test_hash_notation'
			, array(
				'tags' => array(
					array(
						'name' => 'param',
						'content' => '{     Optional. Arguments.<br>    @type string                  $label Label.<br>    @type array&lt;int               $broken A type PHPStan\'s parser rejects.<br>    @type array&lt;string, int|null&gt; ...$0 {         Each entry, wrapped onto         a continuation line.<br>        @type string $0 The first element.<br>        @type int    $1 The second element.<br>    }     @type array {         An entry with no name.<br>        @type bool $flag A flag.<br>    }, }',
						'types' => array( 'array' ),
						'variable' => '$args',
						'hash' => array(
							'content' => 'Optional. Arguments.',
							'items' => array(
								array(
									'types' => array( 'string' ),
									'variable' => '$label',
									'content' => 'Label.',
								),
								array(
									'types' => array( '\\array<int' ),
									'variable' => '$broken',
									'content' => 'A type PHPStan\'s parser rejects.',
								),
								array(
									'types' => array( 'array<string, int|null>' ),
									'variable' => '...$0',
									'content' => '',
									'hash' => array(
										'content' => 'Each entry, wrapped onto a continuation line.',
										'items' => array(
											array(
												'types' => array( 'string' ),
												'variable' => '$0',
												'content' => 'The first element.',
											),
											array(
												'types' => array( 'int' ),
												'variable' => '$1',
												'content' => 'The second element.',
											),
										),
									),
								),
								array(
									'types' => array( 'array' ),
									'variable' => '',
									'content' => '',
									'hash' => array(
										'content' => 'An entry with no name.',
										'items' => array(
											array(
												'types' => array( 'bool' ),
												'variable' => '$flag',
												'content' => 'A flag.',
											),
										),
									),
								),
							),
						),
					),
					array(
						'name' => 'return',
						'content' => '$results {     The results, or false.<br>    @type int $ID Post ID.<br>}',
						'types' => array( 'array', 'false' ),
						'hash' => array(
							'content' => 'The results, or false.',
							'items' => array(
								array(
									'types' => array( 'int' ),
									'variable' => '$ID',
									'content' => 'Post ID.',
								),
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Test that a hash whose braces do not balance is exported as text only.
	 */
	public function test_unbalanced_hash_notation() {

		$this->assertFunctionHasDocs(
			'test_unbalanced_hash_notation'
			, array(
				'tags' => array(
					array(
						'name' => 'param',
						'content' => '{     Arguments.<br>    @type string $label Label.',
						'types' => array( 'array' ),
						'variable' => '$args',
					),
				),
			)
		);
	}

	/**
	 * Test the nested hash of a WordPress core function (wp_register_ability).
	 */
	public function test_core_hash_notation() {

		$func = $this->find_entity_data_in( $this->export_data, 'functions', 'docs_wp_register_ability' );

		$this->assertSame(
			array(
				'content' => 'An associative array of arguments for configuring the ability.',
				'items' => array(
					array(
						'types' => array( 'string' ),
						'variable' => '$label',
						'content' => 'Required. The human-readable label for the ability.',
					),
					array(
						'types' => array( 'string' ),
						'variable' => '$description',
						'content' => 'Required. A detailed description of what the ability does and when it should be used.',
					),
					array(
						'types' => array( 'string' ),
						'variable' => '$category',
						'content' => 'Required. The ability category slug this ability belongs to.<br>The ability category must be registered via <code>wp_register_ability_category()</code> before registering the ability.',
					),
					array(
						'types' => array( 'callable' ),
						'variable' => '$execute_callback',
						'content' => 'Required. A callback function to execute when the ability is invoked.<br>Receives optional mixed input data and must return either a result value (any type) or a <code>WP_Error</code> object on failure.',
					),
					array(
						'types' => array( 'callable' ),
						'variable' => '$permission_callback',
						'content' => 'Required. A callback function to check permissions before execution.<br>Receives optional mixed input data (same as <code>execute_callback</code>) and must return <code>true</code>/<code>false</code> for simple checks, or <code>WP_Error</code> for detailed error responses.',
					),
					array(
						'types' => array( 'array<string, mixed>' ),
						'variable' => '$input_schema',
						'content' => 'Optional. JSON Schema definition for validating the ability\'s input.<br>Must be a valid JSON Schema object defining the structure and constraints for input data. Used for automatic validation and API documentation.',
					),
					array(
						'types' => array( 'array<string, mixed>' ),
						'variable' => '$output_schema',
						'content' => 'Optional. JSON Schema definition for the ability\'s output.<br>Describes the structure of successful return values from <code>execute_callback</code>. Used for documentation and validation.',
					),
					array(
						'types' => array( 'array<string, mixed>' ),
						'variable' => '$meta',
						'content' => '',
						'hash' => array(
							'content' => 'Optional. Additional metadata for the ability.',
							'items' => array(
								array(
									'types' => array( 'array<string, bool|null>' ),
									'variable' => '$annotations',
									'content' => '',
									'hash' => array(
										'content' => 'Optional. Semantic annotations describing the ability\'s behavioral characteristics.<br>These annotations are hints for tooling and documentation.',
										'items' => array(
											array(
												'types' => array( 'bool', 'null' ),
												'variable' => '$readonly',
												'content' => 'Optional. If true, the ability does not modify its environment.',
											),
											array(
												'types' => array( 'bool', 'null' ),
												'variable' => '$destructive',
												'content' => 'Optional. If true, the ability may perform destructive updates to its environment.<br>If false, the ability performs only additive updates.',
											),
											array(
												'types' => array( 'bool', 'null' ),
												'variable' => '$idempotent',
												'content' => 'Optional. If true, calling the ability repeatedly with the same arguments will have no additional effect on its environment.',
											),
										),
									),
								),
								array(
									'types' => array( 'bool' ),
									'variable' => '$public',
									'content' => 'Optional. Whether the ability is meant to be available to clients such as the REST API, MCP, or AI agents. Seeds the default for per-channel flags like <code>$show_in_rest</code>.<br>Defaults to false.',
								),
								array(
									'types' => array( 'bool' ),
									'variable' => '$show_in_rest',
									'content' => 'Optional. Whether to expose this ability in the REST API.<br>When true, the ability can be invoked via HTTP requests.<br>Default is the value of <code>$public</code> when set, false otherwise.',
								),
							),
						),
					),
					array(
						'types' => array( 'string' ),
						'variable' => '$ability_class',
						'content' => 'Optional. Fully-qualified custom class name to instantiate instead of the default <code>WP_Ability</code> class. The custom class must extend <code>WP_Ability</code>. Useful for advanced customization of ability behavior.',
					),
				),
			),
			$func['doc']['tags'][1]['hash']
		);
	}

	/**
	 * Test the type expression reader directly.
	 *
	 * @dataProvider data_type_expressions
	 *
	 * @param string     $text     Text starting with a type.
	 * @param array|null $expected Expected result.
	 */
	public function test_parse_docblock_type_expression( $text, $expected ) {

		$this->assertSame( $expected, \WP_Parser\parse_docblock_type_expression( $text ) );
	}

	/**
	 * Data provider for test_parse_docblock_type_expression().
	 *
	 * @return array[]
	 */
	public function data_type_expressions() {

		return array(
			'generic with whitespace'         => array( 'array<string, mixed> $l10n Desc', array( 'types' => array( 'array<string, mixed>' ), 'length' => 20 ) ),
			'top-level union'                 => array( 'int|false', array( 'types' => array( 'int', 'false' ), 'length' => 9 ) ),
			'class name, no context'          => array( 'WP_Post|null', array( 'types' => array( '\\WP_Post', 'null' ), 'length' => 12 ) ),
			'shape keys are not class names'  => array( 'array{WP_Post: Foo}', array( 'types' => array( 'array{WP_Post: \\Foo}' ), 'length' => 19 ) ),
			'conditional type'                => array( "(\$a is 'U' ? int : string) Desc", array( 'types' => array( "(\$a is 'U' ? int : string)" ), 'length' => 26 ) ),
			'parser exception'                => array( 'array<int $x Desc', null ),
			'unclosed group'                  => array( '(mixed depends on context)', null ),
			'no whitespace after the type'    => array( 'string|null. Desc', null ),
			'description markup'              => array( 'string <code>x</code>', array( 'types' => array( 'string' ), 'length' => 6 ) ),
			'whitespace before a bracket'     => array( 'array <int> $x', null ),
			'callable with a grouped return'  => array( 'callable(mixed): (bool|WP_Error) $cb', array( 'types' => array( 'callable(mixed): (bool|\\WP_Error)' ), 'length' => 32 ) ),
			'empty string'                    => array( '', null ),
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
	public function test_code_snippet_fence_delimiters( $description, $expected_code ) {

		$snippets = \WP_Parser\export_docblock_code_snippets( $description );

		$this->assertCount( 1, $snippets );
		$this->assertSame( $expected_code, $snippets[0]['code'] );
	}

	public function code_snippet_fence_delimiters() {
		return array(
			'smaller runs stay inside a larger fence' => array(
				"````php interactive\n<?php\n```\necho 'inside';\n```\n````",
				"<?php\n```\necho 'inside';\n```",
			),
			'different runs and text do not close a fence' => array(
				"```php interactive\n<?php\n````\necho 'inside';\n``` not a closer\necho 'still inside';\n```",
				"<?php\n````\necho 'inside';\n``` not a closer\necho 'still inside';",
			),
			'arbitrary indentation is removed from content' => array(
				"    ```php interactive\n    <?php\n      echo 'indented';\n\t```",
				"<?php\n  echo 'indented';",
			),
			'three leading spaces are accepted' => array(
				"   ```php interactive\n<?php echo 'three';\n   ```",
				"<?php echo 'three';",
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
	 * Test that a trailing Outputs comment becomes structured output metadata.
	 */
	public function test_code_snippet_output_comment() {

		$snippets = \WP_Parser\export_docblock_code_snippets(
			implode(
				"\n",
				array(
					'```php interactive',
					'$p = WP_HTML_Processor::create_fragment( "<div class=\'free &lt;egg&gt;\'\\tlang-en>" );',
					'$p->next_tag();',
					'foreach ( $p->class_list() as $class_name ) {',
					'  echo "{$class_name} ";',
					'}',
					'// Outputs (JSON-encoded): "free <egg> lang-en "',
					'```',
				)
			)
		);

		$this->assertSame(
			array(
				array(
					'type' => 'php-code-snippet',
					'code' => "\$p = WP_HTML_Processor::create_fragment( \"<div class='free &lt;egg&gt;'\\tlang-en>\" );\n" .
						"\$p->next_tag();\n" .
						"foreach ( \$p->class_list() as \$class_name ) {\n" .
						"  echo \"{\$class_name} \";\n" .
						'}',
					'expected_output' => 'free <egg> lang-en ',
				),
			),
			$snippets
		);
	}

	/**
	 * Test that text after Outputs is exported as a raw one-line value.
	 */
	public function test_inline_code_snippet_output_comment() {

		$snippets = \WP_Parser\export_docblock_code_snippets(
			"```php interactive\necho esc_html( '<egg>' );\n// Outputs: <egg>\n```"
		);

		$this->assertSame(
			array(
				array(
					'type' => 'php-code-snippet',
					'code' => "echo esc_html( '<egg>' );",
					'expected_output' => '<egg>',
				),
			),
			$snippets
		);
	}

	/**
	 * Test that quotes in literal one-line output remain output text.
	 */
	public function test_inline_code_snippet_output_comment_preserves_quotes() {

		$snippets = \WP_Parser\export_docblock_code_snippets(
			"```php interactive\necho 'example';\n// Outputs: \"second \"\n```"
		);

		$this->assertSame( '"second "', $snippets[0]['expected_output'] );
	}

	/**
	 * Test that an expected-output fence remains supported for compatibility.
	 */
	public function test_expected_output_fence_remains_supported() {

		$description = "```php interactive\necho 'example';\n```\n```expected-output\nexample\n```";
		$snippets    = \WP_Parser\export_docblock_code_snippets( $description );

		$this->assertSame( 'example', $snippets[0]['expected_output'] );
		$this->assertStringNotContainsString( 'expected-output', \WP_Parser\strip_docblock_code_snippet_fences( $description ) );
	}

	/**
	 * Test that a trailing Outputs comment block exports human-readable output.
	 */
	public function test_multiline_code_snippet_output_comment() {

		$snippets = \WP_Parser\export_docblock_code_snippets(
			implode(
				"\n",
				array(
					'```php interactive',
					'$values = array(',
					"\t'fruit' => 'apple',",
					');',
					'print_r( $values );',
					'// Outputs:',
					'// Array',
					'// (',
					'//     [fruit] => apple',
					'// )',
					'//',
					'```',
				)
			)
		);

		$this->assertSame(
			array(
				array(
					'type' => 'php-code-snippet',
					'code' => "\$values = array(\n\t'fruit' => 'apple',\n);\nprint_r( \$values );",
					'expected_output' => "Array\n(\n    [fruit] => apple\n)\n",
				),
			),
			$snippets
		);
	}

	/**
	 * Test that a human-readable output block preserves literal Unicode text.
	 */
	public function test_multiline_code_snippet_output_comment_preserves_unicode() {

		$snippets = \WP_Parser\export_docblock_code_snippets(
			"```php interactive\necho 'done';\n// Outputs:\n// ✅ Complete\n```"
		);

		$this->assertSame( '✅ Complete', $snippets[0]['expected_output'] );
	}

	/**
	 * Test that literal output preserves quotes and trailing newlines.
	 */
	public function test_multiline_code_snippet_output_comment_preserves_literal_text() {

		$snippets = \WP_Parser\export_docblock_code_snippets(
			"```php interactive\necho 'done';\n// Outputs:\n// first\n// \"second \"\n//\n//\n```"
		);

		$this->assertSame( "first\n\"second \"\n\n", $snippets[0]['expected_output'] );
	}

	/**
	 * Test that JSON-encoded Outputs comments retain their output value.
	 *
	 * @dataProvider json_encoded_code_snippet_output_comments
	 */
	public function test_json_encoded_code_snippet_output_comments( $comment, $expected_output ) {

		$snippets = \WP_Parser\export_docblock_code_snippets(
			"```php interactive\necho 'example';\n" . $comment . "\n```"
		);

		$this->assertSame(
			array(
				array(
					'type' => 'php-code-snippet',
					'code' => "echo 'example';",
					'expected_output' => $expected_output,
				),
			),
			$snippets
		);
	}

	/**
	 * Returns JSON-encoded output comments with formatting that must survive export.
	 */
	public function json_encoded_code_snippet_output_comments() {

		return array(
			'JSON string preserves trailing whitespace' => array(
				'// Outputs (JSON-encoded): "ends with a space "',
				'ends with a space ',
			),
			'JSON string preserves multiline output with a trailing newline' => array(
				'// Outputs (JSON-encoded): "first\\nsecond\\n"',
				"first\nsecond\n",
			),
			'escaped quotes and tabs' => array(
				'// Outputs (JSON-encoded): "A \\"quote\\" and a \\t tab"',
				"A \"quote\" and a \t tab",
			),
			'whitespace after the JSON string is not output' => array(
				'// Outputs (JSON-encoded): "done"   ',
				'done',
			),
			'literal Unicode remains readable' => array(
				'// Outputs (JSON-encoded): "✅ Complete "',
				'✅ Complete ',
			),
		);
	}

	/**
	 * Test that an Outputs comment before further code remains part of the snippet.
	 */
	public function test_non_trailing_code_snippet_output_comment_remains_code() {

		$snippets = \WP_Parser\export_docblock_code_snippets(
			"```php interactive\necho 'before';\n// Outputs: \"before\"\necho 'after';\n```"
		);

		$this->assertSame(
			array(
				array(
					'type' => 'php-code-snippet',
					'code' => "echo 'before';\n// Outputs: \"before\"\necho 'after';",
				),
			),
			$snippets
		);
	}

	/**
	 * Test that Outputs text is metadata only in a trailing standalone line comment.
	 *
	 * @dataProvider php_code_containing_non_metadata_outputs_text
	 */
	public function test_php_code_containing_non_metadata_outputs_text( $code ) {

		$this->assertSame(
			array(
				array(
					'type' => 'php-code-snippet',
					'code' => $code,
				),
			),
			\WP_Parser\export_docblock_code_snippets( "```php interactive\n" . $code . "\n```" )
		);
	}

	/**
	 * Returns PHP tokens in which Outputs text is ordinary program text.
	 */
	public function php_code_containing_non_metadata_outputs_text() {

		return array(
			'string literal' => array( "echo '// Outputs: not metadata';" ),
			'heredoc body' => array( "echo <<<TEXT\n// Outputs: not metadata\nTEXT;" ),
			'block comment' => array( "echo 'done';\n/* // Outputs: not metadata */" ),
			'comment after code on the same line' => array( "echo 'done'; // Outputs: not metadata" ),
			'ordinary line comment' => array( '// Example containing // Outputs: not metadata' ),
			'text after a PHP closing tag' => array( "<?php echo 'done'; ?>\n// Outputs: not PHP" ),
		);
	}

	/**
	 * Test that code-comment output cannot conflict with an expected-output fence.
	 */
	public function test_code_snippet_output_comment_and_fence_cannot_both_define_output() {

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'declares output both in code and in an expected-output fence' );

		\WP_Parser\export_docblock_code_snippets(
			"```php interactive\necho 'one';\n// Outputs (JSON-encoded): \"one\"\n```\n```expected-output\none\n```"
		);
	}

	/**
	 * Test that a JSON-encoded Outputs comment must contain a JSON string.
	 *
	 * @dataProvider invalid_json_encoded_code_snippet_output_comments
	 */
	public function test_json_encoded_code_snippet_output_comment_requires_json_string( $output ) {

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'The Outputs (JSON-encoded) comment must contain one JSON string.' );

		\WP_Parser\export_docblock_code_snippets(
			"```php interactive\necho 'one';\n// Outputs (JSON-encoded): " . $output . "\n```"
		);
	}

	/**
	 * Returns invalid JSON-encoded output values.
	 */
	public function invalid_json_encoded_code_snippet_output_comments() {

		return array(
			'malformed JSON' => array( '"one' ),
			'JSON value is not a string' => array( '1' ),
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
	 * of collapsing every snippet to the end of the description. Setup Blueprint
	 * fences are removed.
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
				'// Outputs: done',
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

		// No raw PHP fence is left behind in the description.
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
				'    // Outputs: 1',
				'    ```',
				'',
				'    ```php interactive',
				'    <?php echo 2;',
				'    // Outputs: 2',
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
	 * Test that setup Blueprint fences do not accept extra arguments.
	 */
	public function test_setup_blueprint_fence_rejects_extra_arguments() {

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
	 * Test that inline setup Blueprints must belong to an interactive fence.
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
					'// Outputs: first',
					'```',
					'```php interactive',
					'<?php',
					'echo "no leaked inline blueprint";',
					'// Outputs: no leaked inline blueprint',
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
