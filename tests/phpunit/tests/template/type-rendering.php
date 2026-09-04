<?php

/**
 * A test case for rendering type expressions in the templates.
 */

namespace WP_Parser\Tests;

/**
 * Test that a type expression is rendered as text rather than as markup.
 *
 * A generic type is wrapped in angle brackets, which a browser reads as a start
 * tag when they reach it unescaped: `list<string|WP_Post>` is parsed as the
 * text `list` followed by an unknown element, and everything the type says
 * about itself disappears from the page.
 *
 * @group template
 */
class Template_Type_Rendering_Test extends Import_UnitTestCase {

	/**
	 * The type expression the fixture's first function is documented with.
	 *
	 * @var string
	 */
	const GENERIC_TYPE = 'list&lt;string|WP_Post&gt;';

	/**
	 * Set the global post to the imported function with the given name.
	 *
	 * @param string $name The name of the function.
	 *
	 * @return \WP_Post The imported function post.
	 */
	protected function go_to_function( $name ) {

		$posts = get_posts(
			array(
				'post_type'      => $this->importer->post_type_function,
				'posts_per_page' => -1,
			)
		);

		foreach ( $posts as $post ) {
			if ( $name === $post->post_title ) {
				$GLOBALS['post'] = $post;
				setup_postdata( $post );

				return $post;
			}
		}

		$this->fail( "No imported function named {$name}." );
	}

	/**
	 * Test that a generic return type is printed with its brackets escaped.
	 */
	public function test_generic_return_type_is_escaped() {

		$this->go_to_function( 'wp_parser_type_rendering_generic_func' );

		ob_start();
		\WP_Parser\the_return_type();
		$output = ob_get_clean();

		$this->assertSame( self::GENERIC_TYPE, $output );
		$this->assertStringNotContainsString( '<string', $output );
	}

	/**
	 * Test that a generic argument type is printed with its brackets escaped.
	 */
	public function test_generic_argument_type_is_escaped_in_the_prototype() {

		$this->go_to_function( 'wp_parser_type_rendering_generic_func' );

		$prototype = \WP_Parser\get_prototype();

		$this->assertStringContainsString(
			'<span class="type">' . self::GENERIC_TYPE . '</span> <span class="variable">$x</span>'
			, $prototype
		);
		$this->assertStringNotContainsString( '<string', $prototype );
	}

	/**
	 * Test that a generic argument type is printed escaped in the content.
	 */
	public function test_generic_argument_type_is_escaped_in_the_content() {

		$this->go_to_function( 'wp_parser_type_rendering_generic_func' );

		ob_start();
		\WP_Parser\the_content();
		$output = ob_get_clean();

		$this->assertStringContainsString(
			'<span class="type">' . self::GENERIC_TYPE . '</span>'
			, $output
		);
		$this->assertStringNotContainsString( '<string', $output );
	}

	/**
	 * Test that a top-level union return type is printed in full.
	 */
	public function test_top_level_union_return_type_is_printed_in_full() {

		$this->go_to_function( 'wp_parser_type_rendering_union_func' );

		ob_start();
		\WP_Parser\the_return_type();
		$output = ob_get_clean();

		$this->assertSame( 'string|int', $output );
	}

	/**
	 * Test that the separator of a top-level union survives the escaping.
	 *
	 * The types of a tag are parsed into a list which is already split on the
	 * top-level separators, so the separator markup is only reachable through
	 * the filter, where a single element still holds a whole union.
	 */
	public function test_the_or_separator_survives_the_escaping() {

		$this->assertSame(
			array( 'string<span class="wp-parser-item-type-or"> or </span>int' )
			, apply_filters( 'wp_parser_return_type', array( 'string|int' ) )
		);
	}
}
