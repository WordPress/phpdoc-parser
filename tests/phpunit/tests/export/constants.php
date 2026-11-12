<?php

/**
 * A test case for exporting constants.
 */

namespace WP_Parser\Tests;

/**
 * Test that constants are exported correctly.
 */
class Export_Constants extends Export_UnitTestCase {

	/**
	 * Test that file constant docblocks are exported.
	 */
	public function test_file_constant_docblocks() {

		$this->assertEntityHasDocs(
			$this->find_entity_data_in( $this->export_data, 'constants', 'TEST_DEFINED_CONSTANT' )
			, array( 'description' => 'A constant defined with define().' )
		);

		$this->assertEntityHasDocs(
			$this->find_entity_data_in( $this->export_data, 'constants', 'TEST_CONST_CONSTANT' )
			, array( 'description' => 'A constant declared with const.' )
		);
	}

	/**
	 * Test that class constants are exported.
	 */
	public function test_class_constants() {

		$class = $this->find_entity_data_in( $this->export_data, 'classes', 'Test_Constants_Class' );

		$this->assertEntityContains(
			$class
			, 'constants'
			, array(
				'name'            => 'TEST_CLASS_CONSTANT',
				'line'            => 18,
				'end_line'        => 18,
				'value'           => '2',
				'doc.description' => 'A class constant.',
			)
		);
	}
}
