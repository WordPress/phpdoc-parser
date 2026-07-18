<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	class WP_CLI {
		public static function line() {}

		public static function error( $message ) {
			throw new RuntimeException( $message );
		}
	}
}

if ( ! class_exists( 'WP_CLI_Command' ) ) {
	class WP_CLI_Command {}
}

class Command_Import_Test extends WP_UnitTestCase {

	private $files = array();

	/**
	 * @dataProvider invalid_top_level_json_values
	 */
	public function test_import_rejects_json_without_a_top_level_file_list( $json ) {
		$file = $this->write_json_file( $json );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'must contain a top-level list of parsed files' );

		$command = new Command_Import_Test_Command;
		$command->import( array( $file ), array() );
	}

	public function invalid_top_level_json_values() {
		return array(
			'object' => array( '{}' ),
			'null' => array( 'null' ),
			'string' => array( '"parsed files"' ),
			'number' => array( '42' ),
			'boolean' => array( 'false' ),
		);
	}

	public function test_import_rejects_malformed_json() {
		$file = $this->write_json_file( '[}' );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( "can't be decoded" );

		$command = new Command_Import_Test_Command;
		$command->import( array( $file ), array() );
	}

	public function test_import_accepts_a_top_level_file_list() {
		$file    = $this->write_json_file( '[]' );
		$command = new Command_Import_Test_Command;

		$command->import( array( $file ), array() );

		$this->assertSame( array(), $command->imported_data );
	}

	private function write_json_file( $json ) {
		$file = tempnam( sys_get_temp_dir(), 'phpdoc-parser-' );
		file_put_contents( $file, $json );
		$this->files[] = $file;

		return $file;
	}

	public function tearDown() {
		foreach ( $this->files as $file ) {
			unlink( $file );
		}

		parent::tearDown();
	}
}

class Command_Import_Test_Command extends \WP_Parser\Command {

	public $imported_data;

	protected function _do_import( array $data, $skip_sleep = false, $import_ignored = false ) {
		$this->imported_data = $data;
	}
}
