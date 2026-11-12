<?php

/**
 * A test case for exporting the mapped class of a method call.
 */

namespace WP_Parser\Tests;

/**
 * Test that calls on known factory functions are exported with the class they return.
 */
class Export_Mapped_Class_Use extends Export_UnitTestCase {

	/**
	 * Test that a method called on a mapped function is exported with its class.
	 */
	public function test_mapped_function_receiver() {

		$this->assertFunctionUsesMethod(
			'show_help'
			, array(
				'name'     => 'add_help_tab',
				'line'     => 4,
				'end_line' => 4,
				'class'    => 'WP_Screen',
				'static'   => false,
			)
		);
	}
}
