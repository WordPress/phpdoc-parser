<?php

/**
 * A parent test case class for the data export tests.
 */

namespace WP_Parser\Tests;

/**
 * Parent test case for data export tests.
 */
class Export_UnitTestCase extends \PHPUnit_Framework_TestCase {

	/**
	 * The exported data.
	 *
	 * @var string
	 */
	protected static $export_data;

	/**
	 * Parse the file for the current testcase.
	 */
	protected function parse_file() {

		$class_reflector = new \ReflectionClass( $this );
		$file = $class_reflector->getFileName();
		$file = rtrim( $file, 'php' ) . 'inc';
		$path = dirname( $file );

		$export_data = \WP_Parser\parse_files( array( $file ), $path );

		self::$export_data = $export_data[0];
	}

	/**
	 * Parse the file to get teh exported data before the first test.
	 */
	public function setUp() {

		parent::setUp();

		if ( ! self::$export_data ) {
			$this->parse_file();
		}
	}

	/**
	 * Assert that an entity uses another entity.
	 *
	 * @param array  $entity The exported entity data.
	 * @param string $type   The type of thing that this entity should use.
	 * @param array  $used   The expcted data for the thing the entity should use.
	 */
	protected function assertEntityUses( $entity, $type, $used ) {

		$this->assertArrayHasKey( 'uses', $entity );
		$this->assertArrayHasKey( $type, $entity['uses'] );

		$found = false;
		foreach ( $entity['uses'][ $type ] as $exported_used ) {
			if ( $exported_used['line'] == $used['line'] ) {
				$this->assertEquals( $used, $exported_used );
				return;
			}
		}

		$this->markTestFailed( "No matching {$type} used by {$entity['name']}." );
	}

	/**
	 * Assert that a file uses an method.
	 *
	 * @param array $method The expected export data for the method.
	 */
	protected function assertFileUsesMethod( $method ) {

		$this->assertEntityUses( self::$export_data, 'methods', $method );
	}

	/**
	 * Assert that a function uses a method.
	 *
	 * @param string $function_name The name of the function that uses this method.
	 * @param array  $method        The expected exported data for this method.
	 */
	protected function assertFunctionUsesMethod( $function_name, $method ) {

		$function_data = $this->find_entity_data_in(
			self::$export_data
			, 'functions'
			, $function_name
		);

		$this->assertInternalType( 'array', $function_data );
		$this->assertEntityUses( $function_data, 'methods', $method );
	}

	/**
	 * Assert that a method uses a method.
	 *
	 * @param string $class_name  The name of the class that the method is used in.
	 * @param string $method_name The name of the method that uses this method.
	 * @param array  $method      The expected exported data for this method.
	 */
	protected function assertMethodUsesMethod( $class_name, $method_name, $method ) {

		$class_data = $this->find_entity_data_in(
			self::$export_data
			, 'classes'
			, $class_name
		);

		$this->assertInternalType( 'array', $class_data );

		$method_data = $this->find_entity_data_in(
			$class_data
			, 'methods'
			, $method_name
		);

		$this->assertInternalType( 'array', $method_data );
		$this->assertEntityUses( $method_data, 'methods', $method );
	}

	/**
	 * Find the exported data for an entity.
	 *
	 * @param array  $data   The data to search in.
	 * @param string $type   The type of entity.
	 * @param string $entity The name of the function.
	 *
	 * @return array|false The data for the entity, or false if it couldn't be found.
	 */
	protected function find_entity_data_in( $data, $type, $entity_name ) {

		if ( empty( $data[ $type ] ) ) {
			return false;
		}

		foreach ( $data[ $type ] as $entity ) {
			if ( $entity['name'] === $entity_name ) {
				return $entity;
			}
		}

		return false;
	}
}
