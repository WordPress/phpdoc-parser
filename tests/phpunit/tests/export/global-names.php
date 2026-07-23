<?php

/**
 * A test case for exporting global names.
 */

namespace WP_Parser\Tests;

/**
 * Test that synthetic global namespace prefixes are removed from metadata.
 */
class Export_Global_Names extends Export_UnitTestCase {

	/**
	 * Test function metadata.
	 */
	public function test_function_metadata() {
		$function = $this->export_data['functions'][0];

		$this->assertEquals(
			array( 'Global_Alias' => 'Global_Alias_Source' ),
			$function['aliases']
		);
		$this->assertEquals( 'Global_Parameter', $function['arguments'][0]['type'] );
		$this->assertEquals(
			array( 'Global_Doc_Type' ),
			$function['doc']['tags'][0]['types']
		);
	}

	/**
	 * Test class metadata.
	 */
	public function test_class_metadata() {
		$class = $this->export_data['classes'][0];

		$this->assertEquals( 'Global_Parent', $class['extends'] );
		$this->assertEquals( array( 'Global_Interface' ), $class['implements'] );
	}

	/**
	 * Test method-use metadata.
	 */
	public function test_method_use_metadata() {
		$this->assertFileUsesMethod(
			array(
				'name'     => 'global_method',
				'line'     => 14,
				'end_line' => 14,
				'class'    => 'Global_Class',
				'static'   => true,
			)
		);
	}
}
