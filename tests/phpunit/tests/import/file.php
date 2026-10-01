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
class File_Import_Test extends Import_UnitTestCase {

	/**
	 * Test that the term is created for this file.
	 */
	public function test_file_term_created() {

		$terms = get_terms(
			$this->importer->taxonomy_file
			, array( 'hide_empty' => false )
		);

		$this->assertCount( 1, $terms );

		$term = $terms[0];

		$this->assertEquals( 'file.inc', $term->name );
		$this->assertEquals( 'file-inc', $term->slug );
	}

	/**
	 * Test that a post is created for the function.
	 */
	public function test_function_post_created() {

		$posts = get_posts(
			array( 'post_type' => $this->importer->post_type_function )
		);

		$this->assertCount( 1, $posts );

		$post = $posts[0];

		// Check that the post attributes are correct.
		$this->assertEquals(
			'<p>This function is just here for tests. This is its longer description.</p>'
			, $post->post_content
		);
		$this->assertEquals( 'This is a function summary.', $post->post_excerpt );
		$this->assertEquals( 'wp_parser_test_func', $post->post_name );
		$this->assertEquals( 0, $post->post_parent );
		$this->assertEquals( 'wp_parser_test_func', $post->post_title );

		// It should be assigned to the file's taxonomy term.
		$terms = wp_get_object_terms(
			$post->ID
			, $this->importer->taxonomy_file
		);

		$this->assertCount( 1, $terms );
		$this->assertEquals( 'file.inc', $terms[0]->name );

		// It should be assigned to the correct @since taxonomy term.
		$terms = wp_get_object_terms(
			$post->ID
			, $this->importer->taxonomy_since_version
		);

		$this->assertCount( 1, $terms );
		$this->assertEquals( '1.4.0', $terms[0]->name );

		// It should be assigned the correct @package taxonomy term.
		$terms = wp_get_object_terms(
			$post->ID
			, $this->importer->taxonomy_package
		);

		$this->assertCount( 1, $terms );
		$this->assertEquals( 'Something', $terms[0]->name );

		// Check that the metadata was imported.
		$this->assertEquals(
			array(
				array(
					'name' => '$var',
					'default' => null,
					'type' => '',
				),
				array(
					'name' => '$ids',
					'default' => 'array()',
					'type' => 'array' ,
				),
			)
			, get_post_meta( $post->ID, '_wp-parser_args', true )
		);

		$this->assertEquals(
			25
			, get_post_meta( $post->ID, '_wp-parser_line_num', true )
		);

		$this->assertEquals(
			28
			, get_post_meta( $post->ID, '_wp-parser_end_line_num', true )
		);

		$this->assertEquals(
			array(
				array(
					'name' => 'since',
					'content' => '1.4.0',
				),
				array(
					'name' => 'param',
					'content' => 'A string variable which is the first parameter.',
					'types' => array( 'string' ),
					'variable' => '$var',
				),
				array(
					'name' => 'param',
					'content' => 'An array of user IDs.',
					'types' => array( 'int[]' ),
					'variable' => '$ids',
				),
				array(
					'name' => 'return',
					'content' => 'The return type is random. (Not really.)',
					'types' => array( 'mixed' ),
				),
			)
			, get_post_meta( $post->ID, '_wp-parser_tags', true )
		);
	}

	/**
	 * Test that snippet metadata is stored and cleared on later imports.
	 */
	public function test_function_snippet_metadata_imported_and_cleared() {

		$posts = get_posts(
			array( 'post_type' => $this->importer->post_type_function )
		);
		$post  = $posts[0];

		$function_data = $this->export_data['functions'][0];
		$snippets      = array(
			array(
				'type' => 'php-code-snippet',
				'code' => '<?php echo "imported";',
				'blueprint' => 'shared',
			),
		);
		$setup_blueprints = array(
			'shared' => array( 'steps' => array() ),
		);
		$function_data['doc']['code_snippets']    = $snippets;
		$function_data['doc']['setup_blueprints'] = $setup_blueprints;

		$this->importer->import_function( $function_data );

		$this->assertEquals( $snippets, get_post_meta( $post->ID, '_wp-parser_code_snippets', true ) );
		$this->assertEquals( $setup_blueprints, get_post_meta( $post->ID, '_wp-parser_setup_blueprints', true ) );

		unset( $function_data['doc']['code_snippets'], $function_data['doc']['setup_blueprints'] );
		$this->importer->import_function( $function_data );

		$this->assertEquals( array(), get_post_meta( $post->ID, '_wp-parser_code_snippets', true ) );
		$this->assertEquals( array(), get_post_meta( $post->ID, '_wp-parser_setup_blueprints', true ) );
	}

	/**
	 * Test that WordPress metadata slashing does not alter snippet or Blueprint source.
	 */
	public function test_function_snippet_metadata_preserves_backslashes() {

		$posts = get_posts(
			array( 'post_type' => $this->importer->post_type_function )
		);
		$post  = $posts[0];

		$function_data = $this->export_data['functions'][0];
		$snippets      = array(
			array(
				'type' => 'php-code-snippet',
				'code' => '<?php echo \Docs\Example::class;',
				'expected_output' => 'Docs\Example',
				'blueprint' => 'shared',
			),
		);
		$setup_blueprints = array(
			'shared' => array(
				'steps' => array(
					array(
						'step' => 'writeFile',
						'path' => '/wordpress/wp-content/mu-plugins/setup.php',
						'data' => '<?php namespace Docs\Setup;',
					),
				),
				'numericOptions' => (object) array(
					'0' => 'C:\temporary\file.php',
				),
			),
		);
		$function_data['doc']['code_snippets']    = $snippets;
		$function_data['doc']['setup_blueprints'] = $setup_blueprints;

		$this->importer->import_function( $function_data );

		$this->assertEquals( $snippets, get_post_meta( $post->ID, '_wp-parser_code_snippets', true ) );
		$this->assertEquals( $setup_blueprints, get_post_meta( $post->ID, '_wp-parser_setup_blueprints', true ) );
	}

	/**
	 * Test that WordPress metadata slashing does not alter DocBlock tags.
	 */
	public function test_function_tag_metadata_preserves_backslashes() {

		$posts = get_posts(
			array( 'post_type' => $this->importer->post_type_function )
		);
		$post  = $posts[0];

		$function_data = $this->export_data['functions'][0];
		$tags          = array(
			array(
				'name'    => 'see',
				'content' => '',
				'refers'  => '\Docs\Example::method()',
			),
			array(
				'name'     => 'param',
				'content'  => 'A namespaced parameter.',
				'types'    => array( '\Foo', '\Foo\Bar', 'Vendor\Foo' ),
				'variable' => '$var',
			),
		);

		$function_data['doc']['tags'] = $tags;

		$this->importer->import_function( $function_data );

		$this->assertEquals( $tags, get_post_meta( $post->ID, '_wp-parser_tags', true ) );
	}

	/**
	 * Test that WordPress metadata slashing does not alter argument metadata.
	 */
	public function test_function_argument_metadata_preserves_backslashes() {

		$posts = get_posts(
			array( 'post_type' => $this->importer->post_type_function )
		);
		$post  = $posts[0];

		$function_data = $this->export_data['functions'][0];
		$arguments     = array(
			array(
				'name'    => '$leading',
				'default' => null,
				'type'    => '\Foo',
			),
			array(
				'name'    => '$qualified',
				'default' => null,
				'type'    => '\Foo\Bar',
			),
			array(
				'name'    => '$relative',
				'default' => '\Vendor\Foo::DEFAULT_VALUE',
				'type'    => 'Vendor\Foo',
			),
		);

		$function_data['arguments'] = $arguments;

		$this->importer->import_function( $function_data );

		$this->assertEquals( $arguments, get_post_meta( $post->ID, '_wp-parser_args', true ) );
	}

	/**
	 * Test that WordPress metadata slashing does not alter namespace aliases.
	 */
	public function test_function_alias_metadata_preserves_backslashes() {

		$posts = get_posts(
			array( 'post_type' => $this->importer->post_type_function )
		);
		$post  = $posts[0];

		$function_data = $this->export_data['functions'][0];
		$aliases       = array(
			'Leading'   => '\Foo',
			'Qualified' => '\Foo\Bar',
			'Relative'  => 'Vendor\Foo',
		);

		$function_data['aliases'] = $aliases;

		$this->importer->import_function( $function_data );

		$this->assertEquals( $aliases, get_post_meta( $post->ID, '_wp_parser_aliases', true ) );
	}

	/**
	 * Test that WordPress metadata slashing does not alter class metadata.
	 */
	public function test_class_metadata_preserves_backslashes() {

		$properties = array(
			array(
				'name'       => '$example',
				'line'       => 12,
				'end_line'   => 12,
				'default'    => '\Vendor\Foo::DEFAULT_VALUE',
				'static'     => false,
				'visibility' => 'public',
				'doc'        => array(
					'description'      => '',
					'long_description' => '',
					'tags'             => array(
						array(
							'name'     => 'var',
							'content'  => 'A namespaced property.',
							'types'    => array( '\Foo', '\Foo\Bar', 'Vendor\Foo' ),
							'variable' => '',
						),
					),
				),
			),
		);

		$class_data = array(
			'name'       => 'Slashing_Example',
			'namespace'  => 'Vendor\Docs',
			'line'       => 10,
			'end_line'   => 14,
			'final'      => false,
			'abstract'   => false,
			'extends'    => '\Foo\Bar',
			'implements' => array( '\Foo', 'Vendor\Foo' ),
			'properties' => $properties,
			'methods'    => array(),
			'doc'        => array(
				'description'      => 'A class with namespaced relatives.',
				'long_description' => '',
				'tags'             => array(),
			),
		);

		$file_data              = $this->export_data;
		$file_data['functions'] = array();
		$file_data['classes']   = array( $class_data );
		$file_data['hooks']     = array();

		$this->importer->import_file( $file_data, true );

		$posts = get_posts(
			array( 'post_type' => $this->importer->post_type_class )
		);

		$this->assertCount( 1, $posts );

		$post = $posts[0];

		$this->assertEquals( '\Foo\Bar', get_post_meta( $post->ID, '_wp-parser_extends', true ) );
		$this->assertEquals( array( '\Foo', 'Vendor\Foo' ), get_post_meta( $post->ID, '_wp-parser_implements', true ) );
		$this->assertEquals( $properties, get_post_meta( $post->ID, '_wp-parser_properties', true ) );

		// The namespace is already compensated for; it must not be slashed twice.
		$this->assertEquals( 'Vendor\Docs', get_post_meta( $post->ID, '_wp_parser_namespace', true ) );
	}
}
