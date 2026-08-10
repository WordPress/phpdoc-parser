<?php

/**
 * A test case for making exported arguments safe to display.
 */

namespace WP_Parser\Tests;

/**
 * Test that sanitizing arguments doesn't destroy the type expressions in them.
 *
 * The arguments are run through the content filters before they're displayed,
 * which is meant to keep raw phpDoc from introducing unsafe markup. Those
 * filters are written for prose, though, and a type expression isn't prose: a
 * fully qualified class name is all namespace separators, and a generic type is
 * wrapped in what those filters read as an HTML tag.
 */
class Plugin_Args_Safe extends \WP_UnitTestCase {

	/**
	 * Filter the exported arguments the way the templates do.
	 *
	 * @return array[] The filtered arguments.
	 */
	protected function filter_arguments() {

		return apply_filters(
			'wp_parser_get_arguments'
			, array(
				array(
					'name'    => '$prompt',
					'default' => null,
					'type'    => '\WordPress\AiClient\Messages\DTO\MessagePart',
				),
				array(
					'name'    => '$list',
					'default' => null,
					'type'    => 'list<string|\WP_Post>',
				),
				array(
					'name'    => '$evil',
					'default' => null,
					'type'    => '<script>alert(1)</script>',
				),
			)
		);
	}

	/**
	 * Filter arguments shaped the way `get_arguments()` builds them.
	 *
	 * @return array[] The filtered arguments.
	 */
	protected function filter_real_arguments() {

		return apply_filters(
			'wp_parser_get_arguments'
			, array(
				array(
					'name'          => '$x',
					'types'         => array( 'string' ),
					'default_value' => '<b>',
					'desc'          => 'A <b>Bold description.',
				),
				array(
					'name'  => '$cb',
					'types' => array( 'callable(int $a, string $b): bool' ),
				),
				array(
					'name'  => '$post',
					'types' => array( 'callable(\WP_Post $p): void' ),
				),
				array(
					'name'  => '$map',
					'types' => array( 'array<int,  string>' ),
				),
				array(
					'name'  => '$bold',
					'types' => array( 'string' ),
					'desc'  => '<b>hello',
				),
			)
		);
	}

	/**
	 * Test that markup in a default value is neutralized.
	 *
	 * A default value isn't a type expression and isn't escaped where it's
	 * printed, so an unclosed tag in one lands in the page as written.
	 */
	public function test_markup_in_a_default_value_is_neutralized() {

		$arguments = $this->filter_real_arguments();

		$this->assertSame( '<b></b>', $arguments[0]['default_value'] );
	}

	/**
	 * Test that markup in a description is neutralized.
	 */
	public function test_markup_in_a_description_is_neutralized() {

		$arguments = $this->filter_real_arguments();

		$this->assertSame(
			'A <b>Bold description.</b>'
			, $arguments[0]['desc']
		);
	}

	/**
	 * Test that a description which reads as a type expression is neutralized.
	 *
	 * A description is prose wherever it happens to be written with the
	 * characters a type expression is written with, and an unclosed tag in one
	 * swallows the rest of the page.
	 */
	public function test_type_shaped_markup_in_a_description_is_neutralized() {

		$arguments = $this->filter_real_arguments();

		$this->assertSame( '<b>hello</b>', $arguments[4]['desc'] );
	}

	/**
	 * Test that a callable's parameter list survives.
	 */
	public function test_callable_signature_survives() {

		$arguments = $this->filter_real_arguments();

		$this->assertSame(
			array( 'callable(int $a, string $b): bool' )
			, $arguments[1]['types']
		);
	}

	/**
	 * Test that a callable's parameter list keeps its namespace separators.
	 */
	public function test_callable_signature_keeps_namespace_separators() {

		$arguments = $this->filter_real_arguments();

		$this->assertSame(
			array( 'callable(\WP_Post $p): void' )
			, $arguments[2]['types']
		);
	}

	/**
	 * Test that a generic keeps a run of whitespace after its delimiter.
	 */
	public function test_generic_with_repeated_whitespace_survives() {

		$arguments = $this->filter_real_arguments();

		$this->assertSame( array( 'array<int,  string>' ), $arguments[3]['types'] );
	}

	/**
	 * Test that a fully qualified class name keeps its namespace separators.
	 */
	public function test_namespace_separators_survive() {

		$arguments = $this->filter_arguments();

		$this->assertSame(
			'\WordPress\AiClient\Messages\DTO\MessagePart'
			, $arguments[0]['type']
		);
	}

	/**
	 * Test that a generic type keeps its brackets.
	 */
	public function test_generic_type_survives() {

		$arguments = $this->filter_arguments();

		$this->assertSame( 'list<string|\WP_Post>', $arguments[1]['type'] );
	}

	/**
	 * Test that markup in a type isn't passed through as markup.
	 */
	public function test_markup_in_a_type_is_not_passed_through() {

		$arguments = $this->filter_arguments();

		$this->assertStringNotContainsString( '<script>', $arguments[2]['type'] );
	}
}
