<?php

/**
 * A test case for hook exporting.
 */

namespace WP_Parser\Tests;

/**
 * Test that hooks are exported correctly.
 */
class Export_Namespace extends Export_UnitTestCase {

	/**
	 * Test that hook names are standardized on export.
	 */
	public function test_basic_namespace_support() {
		$expected = 'Awesome\\Space';
		$actual   = $this->export_data['functions'][0]['namespace'];

		$this->assertEquals( $expected, $actual, 'Namespace should be parsed' );
	}
}
