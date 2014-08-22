<?php

/**
 * A test case for
 */

namespace WP_Parser\Tests;

/**
 * Test that
 */
class Methods extends Export_UnitTestCase {

	/**
	 * Test that static method use is exported.
	 */
	public function test_static_methods() {

		$this->assertFileUsesMethod(
			array(
				'name'     => array( 'My_Class', 'static_method' ),
				'line'     => 3,
				'end_line' => 3,
			)
		);

		$this->assertFunctionUsesMethod(
			'test'
			, array(
				'name'     => array( 'Another_Class', 'another_method' ),
				'line'     => 8,
				'end_line' => 8,
			)
		);

		$this->assertMethodUsesMethod(
			'My_Class'
			, 'static_method'
			, array(
				'name'     => array( 'Another_Class', 'do_static_stuff' ),
				'line'     => 16,
				'end_line' => 16,
			)
		);

		$this->assertMethodUsesMethod(
			'My_Class'
			, 'static_method'
			, array(
				'name'     => array( 'My_Class', 'do_stuff' ),
				'line'     => 17,
				'end_line' => 17,
			)
		);

		$this->assertMethodUsesMethod(
			'My_Class'
			, 'static_method'
			, array(
				'name'     => array( 'Parent_Class', 'do_parental_stuff' ),
				'line'     => 19,
				'end_line' => 19,
			)
		);
	}

	/**
	 * Test that instance method use is exported.
	 */
	public function test_instance_methods() {

		$this->assertFileUsesMethod(
			array(
				'name'     => array( '$wpdb', 'update' ),
				'line'     => 5,
				'end_line' => 5,
			)
		);

		$this->assertFunctionUsesMethod(
			'test'
			, array(
				'name'     => array( 'get_class()', 'call_method' ),
				'line'     => 10,
				'end_line' => 10,
			)
		);

		$this->assertMethodUsesMethod(
			'My_Class'
			, 'static_method'
			, array(
				'name'     => array( 'My_Class', 'go' ),
				'line'     => 18,
				'end_line' => 18,
			)
		);
	}
}
