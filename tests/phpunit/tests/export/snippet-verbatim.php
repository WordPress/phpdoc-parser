<?php

/**
 * A test case for exporting snippet source verbatim.
 */

namespace WP_Parser\Tests;

/**
 * Test that snippet and Blueprint source is exported exactly as authored.
 *
 * Global-name prefix stripping applies to documentation prose and metadata,
 * never to executable snippet source or Blueprint definitions.
 */
class Export_Snippet_Verbatim extends Export_UnitTestCase {

	/**
	 * Test that snippet code keeps fully qualified global names.
	 */
	public function test_snippet_code_is_verbatim() {

		$this->assertEquals(
			array(
				array(
					'type' => 'php-code-snippet',
					'code' => "<?php\nrequire '/wordpress/wp-load.php';\necho \\Docs_Global::greet();",
					'expected_output' => 'Hello from \\Docs_Global',
					'blueprint' => 'global-setup',
				),
			),
			$this->export_data['functions'][0]['doc']['code_snippets']
		);
	}

	/**
	 * Test that Blueprint definitions keep fully qualified global names.
	 */
	public function test_setup_blueprint_is_verbatim() {

		$this->assertEquals(
			array(
				'global-setup' => array(
					'steps' => array(
						array(
							'step' => 'writeFile',
							'path' => '/wordpress/wp-content/mu-plugins/global-setup.php',
							'data' => "<?php\n\\Docs_Global_Registry::register( '\\Docs_Global' );\n",
						),
					),
				),
			),
			$this->export_data['functions'][0]['doc']['setup_blueprints']
		);
	}
}
