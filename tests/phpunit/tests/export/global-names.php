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
		$this->assertSame( 'true', $function['arguments'][1]['default'] );
		$this->assertSame( 'null', $function['arguments'][2]['default'] );
		$this->assertSame( 'GLOBAL_MODE', $function['arguments'][3]['default'] );
		$this->assertSame( "'\\xC0'", $function['arguments'][4]['default'] );
		$this->assertSame( '\\Vendor\\GLOBAL_MODE', $function['arguments'][5]['default'] );
		$this->assertStringContainsString(
			'{@see Global_Doc_Function()}',
			$function['doc']['long_description']
		);
		$this->assertStringContainsString(
			'\\xC0 as documentation',
			$function['doc']['long_description']
		);
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
	 * Test expression metadata.
	 */
	public function test_expression_metadata() {
		$this->assertSame( 'GLOBAL_VALUE', $this->export_data['constants'][0]['value'] );
		$this->assertSame(
			'global_default(GLOBAL_VALUE)',
			$this->export_data['constants'][1]['value']
		);
		$this->assertSame(
			'\\Vendor\\global_default(\\Vendor\\GLOBAL_VALUE)',
			$this->export_data['constants'][2]['value']
		);

		$class = $this->export_data['classes'][1];
		$this->assertSame( 'false', $class['properties'][0]['default'] );
		$this->assertSame( 'GLOBAL_MODE', $class['properties'][1]['default'] );

		$method = $class['methods'][0];
		$this->assertSame(
			'new Global_Class()',
			$method['uses']['methods'][0]['class']
		);

		$method = $class['methods'][1];
		$this->assertSame(
			'new \\Vendor\\Global_Class()',
			$method['uses']['methods'][0]['class']
		);
	}

	/**
	 * Test method-use metadata.
	 */
	public function test_method_use_metadata() {
		$this->assertFileUsesMethod(
			array(
				'name'     => 'global_method',
				'line'     => 16,
				'end_line' => 16,
				'class'    => 'Global_Class',
				'static'   => true,
			)
		);
	}
}
