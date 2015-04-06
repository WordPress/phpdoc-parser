<?php

/**
 * A test case for exporting function use when one function is defined within another.
 */

namespace WP_Parser\Tests;

/**
 * Test that function use is exported correctly when function declarations are nested.
 */
class Export_Nested_Function_Use extends Export_UnitTestCase {

	/**
	 * Test that the uses data of the outer function is correct.
	 */
	public function test_top_function_uses_correct() {

		$this->assertFunctionUsesFunction(
			'test'
			, array(
				'name'     => 'a_function',
				'line'     => 5,
				'end_line' => 5,
			)
		);

		$this->assertFunctionUsesFunction(
			'test'
			, array(
				'name'     => 'sub_test',
				'line'     => 14,
				'end_line' => 14,
			)
		);

		$this->assertFunctionUsesMethod(
			'test'
			, array(
				'name'     => array( 'My_Class', 'do_things' ),
				'line'     => 16,
				'end_line' => 16,
			)
		);

		$this->assertFunctionNotUsesFunction(
			'test'
			, array(
				'name'     => 'b_function',
				'line'     => 9,
				'end_line' => 9,
			)
		);

		$this->assertFunctionNotUsesMethod(
			'test'
			, array(
				'name'     => array( 'My_Class', 'static_method' ),
				'line'     => 11,
				'end_line' => 11,
			)
		);
	}

	/**
	 * Test that the usages of the nested function is correct.
	 */
	public function test_nested_function_uses_correct() {

		$this->assertFunctionUsesFunction(
			'sub_test'
			, array(
				'name'     => 'b_function',
				'line'     => 9,
				'end_line' => 9,
			)
		);

		$this->assertFunctionUsesMethod(
			'sub_test'
			, array(
				'name'     => array( 'My_Class', 'static_method' ),
				'line'     => 11,
				'end_line' => 11,
			)
		);

		$this->assertFunctionNotUsesFunction(
			'sub_test'
			, array(
				'name'     => 'a_function',
				'line'     => 5,
				'end_line' => 5,
			)
		);

		$this->assertFunctionNotUsesFunction(
			'sub_test'
			, array(
				'name'     => 'sub_test',
				'line'     => 14,
				'end_line' => 14,
			)
		);

		$this->assertFunctionNotUsesMethod(
			'sub_test'
			, array(
				'name'     => array( 'My_Class', 'do_things' ),
				'line'     => 16,
				'end_line' => 16,
			)
		);
	}


	/**
	 * Test that the uses data of the outer method is correct.
	 */
	public function test_method_uses_correct() {

		$this->assertMethodUsesMethod(
			'My_Class'
			, 'a_method'
			, array(
				'name'     => array( 'My_Class', 'do_it', ),
				'line'     => 23,
				'end_line' => 23,
			)
		);

		$this->assertMethodUsesFunction(
			'My_Class'
			, 'a_method'
			, array(
				'name'     => 'do_things',
				'line'     => 32,
				'end_line' => 32,
			)
		);

		$this->assertMethodNotUsesFunction(
			'My_Class'
			, 'a_method'
			, array(
				'name'     => 'b_function',
				'line'     => 27,
				'end_line' => 27,
			)
		);

		$this->assertMethodNotUsesMethod(
			'My_Class'
			, 'a_method'
			, array(
				'name'     => array( 'My_Class', 'a_method' ),
				'line'     => 29,
				'end_line' => 29,
			)
		);
	}

	/**
	 * Test that the usages of the nested function within a method is correct.
	 */
	public function test_nested_function_in_method_uses_correct() {

		$this->assertFunctionUsesFunction(
			'sub_method_test'
			, array(
				'name'     => 'b_function',
				'line'     => 27,
				'end_line' => 27,
			)
		);

		$this->assertFunctionUsesMethod(
			'sub_method_test'
			, array(
				'name'     => array( 'My_Class', 'a_method' ),
				'line'     => 29,
				'end_line' => 29,
			)
		);

		$this->assertFunctionNotUsesMethod(
			'sub_method_test'
			, array(
				'name'     => array( 'My_Class', 'do_it', ),
				'line'     => 23,
				'end_line' => 23,
			)
		);

		$this->assertFunctionNotUsesFunction(
			'sub_method_test'
			, array(
				'name'     => 'do_things',
				'line'     => 32,
				'end_line' => 32,
			)
		);
	}
}
