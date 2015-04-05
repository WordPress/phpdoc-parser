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
	protected $export_data;

	/**
	 * Parse the file for the current testcase.
	 */
	protected function parse_file() {

		$class_reflector = new \ReflectionClass( $this );
		$file = $class_reflector->getFileName();
		$file = rtrim( $file, 'php' ) . 'inc';
		$path = dirname( $file );

		$export_data = \WP_Parser\parse_files( array( $file ), $path );

		$this->export_data = $export_data[0];
	}

	/**
	 * Parse the file to get the exported data before the first test.
	 */
	public function setUp() {

		parent::setUp();

		if ( ! $this->export_data ) {
			$this->parse_file();
		}
	}

	/**
	 * Assert that an entity contains another entity.
	 *
	 * @param array  $entity   The exported entity data.
	 * @param string $type     The type of thing that this entity should contain.
	 * @param array  $expected The expcted data for the thing the entity should contain.
	 */
	protected function assertEntityContains( $entity, $type, $expected ) {

		$this->assertArrayHasKey( $type, $entity );

		$found = false;
		foreach ( $entity[ $type ] as $exported ) {
			if ( $exported['line'] == $expected['line'] ) {
				foreach ( $expected as $key => $expected_value ) {
					$this->assertEquals( $expected_value, $exported[ $key ] );
				}

				return;
			}
		}

		$this->fail( "No matching {$type} contained by {$entity['name']}." );
	}

	/**
	 * Assert that a file contains the declaration of a hook.
	 *
	 * @param array $hook The expected export data for the hook.
	 */
	protected function assertFileContainsHook( $hook ) {

		$this->assertEntityContains( $this->export_data, 'hooks', $hook );
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

		$this->fail( "No matching {$type} used by {$entity['name']}." );
	}

	/**
	 * Assert that a file uses an method.
	 *
	 * @param array $method The expected export data for the method.
	 */
	protected function assertFileUsesMethod( $method ) {

		$this->assertEntityUses( $this->export_data, 'methods', $method );
	}

	/**
	 * Assert that a function uses a method.
	 *
	 * @param string $function_name The name of the function that uses this method.
	 * @param array  $method        The expected exported data for this method.
	 */
	protected function assertFunctionUsesMethod( $function_name, $method ) {

		$function_data = $this->find_entity_data_in(
			$this->export_data
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
			$this->export_data
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
	 * Assert that an entity has a docblock.
	 *
	 * @param array  $entity  The exported entity data.
	 * @param array  $docs    The expcted data for the entity's docblock.
	 * @param string $doc_key The key in the entity array that should hold the docs.
	 */
	protected function assertEntityHasDocs( $entity, $docs, $doc_key = 'doc' ) {

		$this->assertArrayHasKey( $doc_key, $entity );

		$found = false;
		foreach ( $docs as $key => $expected_value ) {
			$this->assertEquals( $expected_value, $entity[ $doc_key ][ $key ] );
		}
	}

	/**
	 * Assert that a file has a docblock.
	 *
	 * @param array $docs The expected data for the file's docblock.
	 */
	protected function assertFileHasDocs( $docs ) {

		$this->assertEntityHasDocs( $this->export_data, $docs, 'file' );
	}

	/**
	 * Assert that a function has a docblock.
	 *
	 * @param array $func The function name.
	 * @param array $docs The expected data for the function's docblock.
	 */
	protected function assertFunctionHasDocs( $func, $docs ) {

		$func = $this->find_entity_data_in( $this->export_data, 'functions', $func );
		$this->assertEntityHasDocs( $func, $docs );
	}

	/**
	 * Assert that a class has a docblock.
	 *
	 * @param array $class The class name.
	 * @param array $docs  The expected data for the class's docblock.
	 */
	protected function assertClassHasDocs( $class, $docs ) {

		$class = $this->find_entity_data_in( $this->export_data, 'classes', $class );
		$this->assertEntityHasDocs( $class, $docs );
	}

	/**
	 * Assert that a method has a docblock.
	 *
	 * @param string $class  The name of the class that the method is used in.
	 * @param string $method The method name.
	 * @param array  $docs   The expected data for the methods's docblock.
	 */
	protected function assertMethodHasDocs( $class, $method, $docs ) {

		$class = $this->find_entity_data_in( $this->export_data, 'classes', $class );
		$this->assertInternalType( 'array', $class );

		$method = $this->find_entity_data_in( $class, 'methods', $method );
		$this->assertEntityHasDocs( $method, $docs );
	}

	/**
	 * Assert that a property has a docblock.
	 *
	 * @param string $class    The name of the class that the method is used in.
	 * @param string $property The property name.
	 * @param array  $docs     The expected data for the property's docblock.
	 */
	protected function assertPropertyHasDocs( $class, $property, $docs ) {

		$class = $this->find_entity_data_in( $this->export_data, 'classes', $class );
		$this->assertInternalType( 'array', $class );

		$property = $this->find_entity_data_in( $class, 'properties', $property );
		$this->assertEntityHasDocs( $property, $docs );
	}

	/**
	 * Assert that a hook has a docblock.
	 *
	 * @param array $hook The hook name.
	 * @param array $docs The expected data for the hook's docblock.
	 */
	protected function assertHookHasDocs( $hook, $docs ) {

		$hook = $this->find_entity_data_in( $this->export_data, 'hooks', $hook );
		$this->assertEntityHasDocs( $hook, $docs );
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
	protected function find_entity_data_in( $data, $type, $entity ) {

		if ( empty( $data[ $type ] ) ) {
			return false;
		}

		foreach ( $data[ $type ] as $entity_data ) {
			if ( $entity_data['name'] === $entity ) {
				return $entity_data;
			}
		}

		return false;
	}
}
