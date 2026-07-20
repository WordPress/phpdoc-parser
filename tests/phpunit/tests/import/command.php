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
			'empty object' => array( '{}' ),
			'non-empty object' => array( '{"path":"wp-includes/version.php"}' ),
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

	/**
	 * @dataProvider invalid_file_entries
	 */
	public function test_import_rejects_invalid_file_entries( $json ) {
		$file = $this->write_json_file( $json );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'entry 1 must contain a parsed file object with a path and file metadata' );

		$command = new Command_Import_Test_Command;
		$command->import( array( $file ), array() );
	}

	public function invalid_file_entries() {
		return array(
			'null' => array( '[null]' ),
			'number' => array( '[42]' ),
			'string' => array( '["parsed file"]' ),
			'list' => array( '[[]]' ),
			'empty object' => array( '[{}]' ),
			'missing file metadata' => array( '[{"path":"example.php"}]' ),
			'non-string path' => array( '[{"path":42,"file":{"description":"","long_description":"","tags":[]}}]' ),
			'empty path' => array( '[{"path":"","file":{"description":"","long_description":"","tags":[]}}]' ),
			'non-object file metadata' => array( '[{"path":"example.php","file":[]}]' ),
			'empty object file metadata' => array( '[{"path":"example.php","file":{}}]' ),
			'missing description' => array( '[{"path":"example.php","file":{"long_description":"","tags":[]}}]' ),
			'non-string description' => array( '[{"path":"example.php","file":{"description":42,"long_description":"","tags":[]}}]' ),
			'missing long description' => array( '[{"path":"example.php","file":{"description":"","tags":[]}}]' ),
			'non-string long description' => array( '[{"path":"example.php","file":{"description":"","long_description":42,"tags":[]}}]' ),
			'missing tags' => array( '[{"path":"example.php","file":{"description":"","long_description":""}}]' ),
			'non-array tags' => array( '[{"path":"example.php","file":{"description":"","long_description":"","tags":42}}]' ),
			'empty object tags' => array( '[{"path":"example.php","file":{"description":"","long_description":"","tags":{}}}]' ),
			'named object tags' => array( '[{"path":"example.php","file":{"description":"","long_description":"","tags":{"name":"since","content":"1.0"}}}]' ),
			'numeric-key object tags' => array( '[{"path":"example.php","file":{"description":"","long_description":"","tags":{"0":{"name":"since","content":"1.0"}}}}]' ),
		);
	}

	public function test_import_accepts_a_top_level_file_list() {
		$file    = $this->write_json_file( '[]' );
		$command = new Command_Import_Test_Command;

		$command->import( array( $file ), array() );

		$this->assertSame( array(), $command->imported_data );
	}

	public function test_import_accepts_a_parsed_file_entry() {
		$file    = $this->write_json_file( '[{"file":{"description":"","long_description":"","tags":[{"name":"since","content":"1.0"}]},"path":"example.php","root":"/tmp"}]' );
		$command = new Command_Import_Test_Command;

		$command->import( array( $file ), array() );

		$this->assertSame(
			array(
				array(
					'file' => array(
						'description' => '',
						'long_description' => '',
						'tags' => array(
							array(
								'name' => 'since',
								'content' => '1.0',
							),
						),
					),
					'path' => 'example.php',
					'root' => '/tmp',
				),
			),
			$command->imported_data
		);
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
