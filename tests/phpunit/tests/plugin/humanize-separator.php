<?php

/**
 * A test case for humanizing the union type separator.
 */

namespace WP_Parser\Tests;

/**
 * Test that only top-level union separators are humanized.
 */
class Plugin_Humanize_Separator extends \WP_UnitTestCase {

	/**
	 * The plugin instance under test.
	 *
	 * @var \WP_Parser\Plugin
	 */
	protected $plugin;

	/**
	 * Set up before the tests.
	 */
	public function set_up() {

		parent::set_up();

		$this->plugin = new \WP_Parser\Plugin();
	}

	/**
	 * Test that top-level union separators are humanized.
	 *
	 * @dataProvider data_humanized_separators
	 *
	 * @param string $type     The type to humanize.
	 * @param string $expected The expected humanized type.
	 */
	public function test_humanize_separator( $type, $expected ) {

		$this->assertSame( $expected, $this->plugin->humanize_separator( $type ) );
	}

	/**
	 * Data provider for humanized separators.
	 *
	 * @return array[] The type, and its expected humanized form.
	 */
	public function data_humanized_separators() {

		$separator = '<span class="wp-parser-item-type-or"> or </span>';

		return array(
			'top-level union' => array(
				'string|int'
				, 'string' . $separator . 'int'
			),
			'union inside brackets' => array(
				'list<string|\WP_Post|array>'
				, 'list<string|\WP_Post|array>'
			),
			'top-level union alongside a bracketed union' => array(
				'Foo|list<int|string>'
				, 'Foo' . $separator . 'list<int|string>'
			),
		);
	}
}
