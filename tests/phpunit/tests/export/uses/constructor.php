<?php

/**
 * A test case for exporting constructor method use.
 */

namespace WP_Parser\Tests;

/**
 * Test that use of the __construct() method is exported for new Class() statements.
 */
class Export_Constructor_Use extends Export_UnitTestCase {

	/**
	 * Test that use is exported when the class name is used explicitly.
	 */
	public function test_new_class() {

		$this->assertFileUsesMethod(
			array(
				'name'     => '__construct',
				'line'     => 3,
				'end_line' => 3,
				'class'    => '\WP_Query',
				'static'   => false,
			)
		);

		$this->assertFunctionUsesMethod(
			'test'
			, array(
				'name'     => '__construct',
				'line'     => 6,
				'end_line' => 6,
				'class'    => '\My_Class',
				'static'   => false,
			)
		);
	}

	/**
	 * Test that use is exported when the self keyword is used.
	 */
	public function test_new_self() {

		$this->assertMethodUsesMethod(
			'My_Class'
			, 'instance'
			, array(
				'name'     => '__construct',
				'line'     => 12,
				'end_line' => 12,
				'class'    => '\My_Class',
				'static'   => false,
			)
		);
	}

	/**
	 * Test that use is exported when the parent keyword is used.
	 */
	public function test_new_parent() {

		$this->assertMethodUsesMethod(
			'My_Class'
			, 'parent'
			, array(
				'name'     => '__construct',
				'line'     => 16,
				'end_line' => 16,
				'class'    => '\Parent_Class',
				'static'   => false,
			)
		);
	}

	/**
	 * Test that use is exported when a variable is used.
	 */
	public function test_new_variable() {

		$this->assertFileUsesMethod(
			array(
				'name'     => '__construct',
				'line'     => 20,
				'end_line' => 20,
				'class'    => '$class',
				'static'   => false,
			)
		);
	}
}
