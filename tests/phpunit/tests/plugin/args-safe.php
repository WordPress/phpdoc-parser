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
