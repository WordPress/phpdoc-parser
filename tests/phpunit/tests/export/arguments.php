<?php
/**
 * A test case for exporting function arguments.
 *
 * @package WP_Parser\Tests
 */

declare( strict_types = 1 );

namespace WP_Parser\Tests;

/**
 * Test that function arguments are exported correctly.
 */
class Export_Arguments extends Export_UnitTestCase {

	/**
	 * Test that variadic arguments are flagged so the ellipsis can be displayed.
	 */
	public function test_variadic_argument_is_flagged(): void {
		$this->assertSame(
			array(
				array(
					'name'    => '$separator',
					'default' => null,
					'type'    => '',
				),
				array(
					'name'     => '$pieces',
					'default'  => null,
					'type'     => '',
					'variadic' => true,
				),
			),
			$this->export_data['functions'][0]['arguments']
		);
	}
}
