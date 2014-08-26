<?php

/**
 * A test case for exporting docblocks.
 */

namespace WP_Parser\Tests;
include( '/Users/johngrimes/plugins/jds-dev-tools.php' );// TODO
/**
 * Test that docblocks are exported correctly.
 */
class Export_Docblocks extends Export_UnitTestCase {

	/**
	 * Test that line breaks are removed when the description is exported.
	 */
	public function test_linebreaks_removed() {

		$this->assertStringMatchesFormat(
			'%s'
			, $this->export_data['classes'][0]['doc']['long_description']
		);
	}
}
