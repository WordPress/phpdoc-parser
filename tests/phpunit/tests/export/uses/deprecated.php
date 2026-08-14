<?php

/**
 * A test case for exporting the version of a deprecation.
 */

namespace WP_Parser\Tests;

/**
 * Test that the deprecation version is exported for the deprecating call itself.
 */
class Export_Deprecated_Function_Use extends Export_UnitTestCase {

	/**
	 * Test that the version is exported for the _deprecated_function() call.
	 */
	public function test_deprecating_call_has_version() {

		$this->assertFunctionUsesFunction(
			'old_thing'
			, array(
				'name'                => '_deprecated_function',
				'line'                => 5,
				'end_line'            => 5,
				'deprecation_version' => '6.1.0',
			)
		);
	}

	/**
	 * Test that the version isn't exported for a call preceding the deprecation.
	 */
	public function test_preceding_call_has_no_version() {

		$this->assertFunctionUsesFunction(
			'old_thing'
			, array(
				'name'     => 'do_something_first',
				'line'     => 4,
				'end_line' => 4,
			)
		);
	}
}
