<?php

/**
 * A test case for hook exporting.
 */

namespace WP_Parser\Tests;

/**
 * Test that hooks are exported correctly.
 */
class Export_Namespace extends Export_UnitTestCase {

	/**
	 * Test that hook names are standardized on export.
	 */
	public function test_basic_namespace_support() {
		$expected = 'Awesome\\Space';
		$actual   = $this->export_data['functions'][0]['namespace'];

		$this->assertEquals( $expected, $actual, 'Namespace should be parsed' );
	}

	/**
	 * Test that class names in types, including inside generics, resolve against the namespace and its aliases.
	 */
	public function test_type_names_resolve_against_namespace() {
		$tag = $this->export_data['functions'][1]['doc']['tags'][0];

		$this->assertSame(
			array(
				'\\Awesome\\Space\\Local',
				'list<\\Other\\Place\\Thing>',
				'array<string, \\Absolute\\Name|\\Other\\Place\\Thing\\Child>',
			),
			$tag['types']
		);
		$this->assertSame( '$value', $tag['variable'] );
	}
}
