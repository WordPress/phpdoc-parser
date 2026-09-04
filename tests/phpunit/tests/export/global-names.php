<?php

/**
 * A test case for exporting global names.
 */

namespace WP_Parser\Tests;

/**
 * Test that synthetic global namespace prefixes are removed from resolved
 * metadata while documentation text is exported as authored.
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
	 * Test that inline references in prose are preserved as authored.
	 */
	public function test_prefixed_inline_reference_metadata() {
		$function = $this->export_data['functions'][1];

		$this->assertStringContainsString(
			'{@see \\Global_Doc_Function()}',
			$function['doc']['long_description']
		);
	}

	/**
	 * Test that see tag references are preserved as authored.
	 */
	public function test_see_tag_reference_metadata() {
		$tags = $this->export_data['functions'][1]['doc']['tags'];

		$this->assertSame( '\\Global_See_Target()', $tags[0]['refers'] );
		$this->assertSame( 'Global_See_Plain()', $tags[1]['refers'] );
	}

	/**
	 * Test that link tag targets are preserved as authored.
	 */
	public function test_link_tag_metadata() {
		$tags = $this->export_data['functions'][1]['doc']['tags'];

		$this->assertSame( 'https://example.com/reference', $tags[2]['link'] );
	}

	/**
	 * Test that prefixes synthesized by type resolution stay stripped.
	 */
	public function test_prefixed_tag_type_metadata() {
		$tags = $this->export_data['functions'][1]['doc']['tags'];

		$this->assertEquals( array( 'Global_Prefixed_Type' ), $tags[3]['types'] );
	}

	/**
	 * Test that namespaced inline references keep their prefix.
	 */
	public function test_namespaced_inline_reference_metadata() {
		$function = $this->export_data['functions'][1];

		$this->assertStringContainsString(
			'{@see \\Vendor\\Thing::m()}',
			$function['doc']['long_description']
		);
	}

	/**
	 * Test that inline references in code samples are preserved.
	 */
	public function test_code_sample_inline_reference_metadata() {
		$function = $this->export_data['functions'][1];

		$this->assertStringContainsString(
			'// Renders {@see \\Global_Widget::render()}.',
			$function['doc']['long_description']
		);
	}

	/**
	 * Test that inline references in inline code spans are preserved.
	 */
	public function test_inline_code_span_inline_reference_metadata() {
		$function = $this->export_data['functions'][1];

		$this->assertStringContainsString(
			'<code>{@see \\Global_Inline::method()}</code>',
			$function['doc']['long_description']
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
