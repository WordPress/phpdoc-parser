<?php

/**
 * A test case for exporting method use.
 */

namespace WP_Parser\Tests;

/**
 * Test that method use is exported correctly.
 */
class Export_Method_Use extends Export_UnitTestCase {

	/**
	 * Test that static method use is exported.
	 */
	public function test_static_methods() {

		$this->assertFileUsesMethod(
			array(
				'name'     => 'static_method',
				'line'     => 3,
				'end_line' => 3,
				'class'    => 'My_Class',
				'static'   => true,
			)
		);

		$this->assertFunctionUsesMethod(
			'test'
			, array(
				'name'     => 'another_method',
				'line'     => 8,
				'end_line' => 8,
				'class'    => 'Another_Class',
				'static'   => true,
			)
		);

		$this->assertMethodUsesMethod(
			'My_Class'
			, 'static_method'
			, array(
				'name'     => 'do_static_stuff',
				'line'     => 16,
				'end_line' => 16,
				'class'    => 'Another_Class',
				'static'   => true,
			)
		);

		$this->assertMethodUsesMethod(
			'My_Class'
			, 'static_method'
			, array(
				'name'     => 'do_stuff',
				'line'     => 17,
				'end_line' => 17,
				'class'    => 'My_Class',
				'static'   => true,
			)
		);

		$this->assertMethodUsesMethod(
			'My_Class'
			, 'static_method'
			, array(
				'name'     => 'do_parental_stuff',
				'line'     => 19,
				'end_line' => 19,
				'class'    => 'Parent_Class',
				'static'   => true,
			)
		);
	}

	/**
	 * Test that instance method use is exported.
	 */
	public function test_instance_methods() {

		$this->assertFileUsesMethod(
			array(
				'name'     => 'update',
				'line'     => 5,
				'end_line' => 5,
				'class'    => '$wpdb',
				'static'   => false,
			)
		);

		$this->assertFunctionUsesMethod(
			'test'
			, array(
				'name'     => 'call_method',
				'line'     => 10,
				'end_line' => 10,
				'class'    => 'get_class()',
				'static'   => false,
			)
		);

		$this->assertMethodUsesMethod(
			'My_Class'
			, 'static_method'
			, array(
				'name'     => 'go',
				'line'     => 18,
				'end_line' => 18,
				'class'    => 'My_Class',
				'static'   => false,
			)
		);
	}
}
