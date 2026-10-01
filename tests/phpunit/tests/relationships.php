<?php

/**
 * A test case for mapping item names to related posts.
 */

namespace WP_Parser\Tests;

/**
 * Test that item names are mapped to related posts correctly.
 *
 * @group relationships
 */
class Relationships_Test extends \WP_UnitTestCase {

	/**
	 * The relationships instance under test.
	 *
	 * @var \WP_Parser\Relationships
	 */
	protected $relationships;

	/**
	 * Set up before each test.
	 */
	public function set_up() {

		parent::set_up();

		$this->relationships = new \WP_Parser\Relationships;
	}

	/**
	 * Test that a fully qualified name resolves in the global scope only.
	 */
	public function test_names_to_slugs_fully_qualified() {

		$this->assertSame(
			array( 'foo-bar' )
			, $this->relationships->names_to_slugs( '\Foo\bar', 'Baz' )
		);
	}

	/**
	 * Test that an unqualified name resolves in the namespace, then globally.
	 */
	public function test_names_to_slugs_unqualified() {

		$this->assertSame(
			array( 'baz-foo-bar', 'foo-bar' )
			, $this->relationships->names_to_slugs( 'Foo\bar', 'Baz' )
		);
	}

	/**
	 * Test that only the first matching scope for a name produces an ID.
	 */
	public function test_get_ids_for_slugs_first_matching_scope_wins() {

		$this->assertSame(
			array( 'baz-foo' => 5 )
			, $this->relationships->get_ids_for_slugs(
				array( array( 'baz-foo', 'foo' ) )
				, array(
					'baz-foo' => 5,
					'foo'     => 7,
				)
			)
		);
	}

	/**
	 * Test that a slug with no ID in any scope produces no ID.
	 */
	public function test_get_ids_for_slugs_unknown_slug_is_ignored() {

		$this->assertSame(
			array()
			, $this->relationships->get_ids_for_slugs(
				array( array( 'baz-foo', 'foo' ) )
				, array( 'quux' => 9 )
			)
		);
	}

	/**
	 * Test that an empty slug map produces no connections at all.
	 */
	public function test_ending_import_with_empty_slug_map_makes_no_connections() {

		global $wpdb;

		$this->relationships->wp_parser_starting_import();

		$from_id = self::factory()->post->create(
			array( 'post_type' => 'wp-parser-function' )
		);

		$this->relationships->slugs_to_ids  = array();
		$this->relationships->relationships = array(
			'wp-parser-function' => array(
				$from_id => array(
					'wp-parser-function' => array( array( 'baz-foo', 'foo' ) ),
				),
			),
		);

		$this->relationships->wp_parser_ending_import();

		// The unresolvable slugs must not survive as raw candidate arrays.
		$this->assertSame(
			array()
			, $this->relationships->relationships['wp-parser-function'][ $from_id ]['wp-parser-function']
		);

		$connections = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}p2p WHERE p2p_from = %d"
				, $from_id
			)
		);

		$this->assertSame( array(), $connections );
	}
}
