<?php

/**
 * A test case for the diff normalization script.
 */

namespace WP_Parser\Tests;

/**
 * Test that generated JSON is normalized for stable diffs.
 */
class Prep_Diff_Test extends \WP_UnitTestCase {

	/**
	 * Normalizes JSON by running prep-diff.php as a standalone script.
	 *
	 * @param string $json JSON to normalize.
	 *
	 * @return string Normalized JSON.
	 */
	protected function normalize( $json ) {

		$script = dirname( __DIR__, 3 ) . '/prep-diff.php';

		$descriptor_spec = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$process = proc_open(
			escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $script ),
			$descriptor_spec,
			$pipes
		);

		$this->assertIsResource( $process, 'Unable to start prep-diff.php.' );

		fwrite( $pipes[0], $json );
		fclose( $pipes[0] );

		$output = stream_get_contents( $pipes[1] );
		$error  = stream_get_contents( $pipes[2] );

		fclose( $pipes[1] );
		fclose( $pipes[2] );

		$status = proc_close( $process );

		$this->assertSame( 0, $status, trim( $error ) );

		return $output;
	}

	/**
	 * Returns generated JSON for a build.
	 *
	 * @return string JSON with parser output in source order.
	 */
	protected function get_json() {

		return json_encode(
			array(
				array(
					'root'       => '/tmp/build-a',
					'path'       => 'beta.php',
					'call_graph' => array(
						array( 'name' => 'zeta', 'line' => 9, 'end_line' => 9 ),
						array( 'name' => 'alpha', 'line' => 3, 'end_line' => 3 ),
					),
					'functions'  => array(
						array(
							'uses'      => array(
								'functions' => array(
									array( 'name' => 'zeta', 'line' => 9, 'end_line' => 9 ),
									array( 'name' => 'alpha', 'line' => 3, 'end_line' => 3 ),
								),
							),
							'line'      => 20,
							'name'      => 'beta',
							'namespace' => 'global',
							'arguments' => array(
								array( 'name' => '$first', 'type' => '\\Global_Type', 'default' => '\\false' ),
								array( 'name' => '$second', 'type' => '' ),
							),
							'hooks'     => array(
								array( 'name' => '\\x09tab', 'type' => 'action', 'line' => 10, 'end_line' => 10 ),
							),
							'doc'       => array(
								'tags'             => array(
									array( 'name' => 'since', 'content' => '1.0.0' ),
									array( 'name' => 'param', 'content' => 'First.', 'variable' => '$first' ),
								),
								'long_description' => '',
								'description'      => 'Calls {@see \\alpha()}; preserves \\xC0.',
							),
						),
					),
				),
				array(
					'path' => 'alpha.php',
					'root' => '/tmp/build-a',
				),
			)
		);
	}

	/**
	 * Returns generated JSON for an equivalent build with shuffled output.
	 *
	 * @return string JSON with the same content in a different order.
	 */
	protected function get_shuffled_json() {

		return json_encode(
			array(
				array(
					'root' => '/tmp/build-b',
					'path' => 'alpha.php',
				),
				array(
					'path'       => 'beta.php',
					'root'       => '/tmp/build-b',
					'call_graph' => array(
						array( 'end_line' => 90, 'line' => 90, 'name' => 'zeta' ),
						array( 'end_line' => 30, 'line' => 30, 'name' => 'alpha' ),
					),
					'functions'  => array(
						array(
							'namespace' => 'global',
							'name'      => 'beta',
							'line'      => 98,
							'doc'       => array(
								'description'      => 'Calls {@see \\alpha()}; preserves \\xC0.',
								'long_description' => '',
								'tags'             => array(
									array( 'name' => 'since', 'content' => '1.0.0' ),
									array( 'name' => 'param', 'content' => 'First.', 'variable' => '$first' ),
								),
							),
							'arguments' => array(
								array( 'default' => 'false', 'type' => 'Global_Type', 'name' => '$first' ),
								array( 'type' => '', 'name' => '$second' ),
							),
							'hooks'     => array(
								array( 'end_line' => 100, 'line' => 100, 'type' => 'action', 'name' => '\\x09tab' ),
							),
							'uses'      => array(
								'functions' => array(
									array( 'end_line' => 30, 'line' => 30, 'name' => 'alpha' ),
									array( 'end_line' => 90, 'line' => 90, 'name' => 'zeta' ),
								),
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Test that equivalent output with incidental differences normalizes identically.
	 */
	public function test_equivalent_output_normalizes_identically() {

		$this->assertSame(
			$this->normalize( $this->get_json() ),
			$this->normalize( $this->get_shuffled_json() )
		);
	}

	/**
	 * Test that unordered parser collections are sorted.
	 */
	public function test_unordered_collections_are_sorted() {

		$decoded = json_decode( $this->normalize( $this->get_json() ), true );

		$this->assertSame( 'alpha.php', $decoded[0]['path'] );
		$this->assertSame(
			array( 'alpha', 'zeta' ),
			array_column( $decoded[1]['call_graph'], 'name' )
		);
		$this->assertSame(
			array( 'alpha', 'zeta' ),
			array_column( $decoded[1]['functions'][0]['uses']['functions'], 'name' )
		);
		$this->assertSame(
			array( 'arguments', 'doc', 'hooks', 'line', 'name', 'namespace', 'uses' ),
			array_keys( $decoded[1]['functions'][0] )
		);
	}

	/**
	 * Test that ordered documentation data is left in source order.
	 */
	public function test_documentation_order_is_preserved() {

		$decoded = json_decode( $this->normalize( $this->get_json() ), true );

		$this->assertSame(
			array( '$first', '$second' ),
			array_column( $decoded[1]['functions'][0]['arguments'], 'name' )
		);
		$this->assertSame(
			array( 'since', 'param' ),
			array_column( $decoded[1]['functions'][0]['doc']['tags'], 'name' )
		);
	}

	/**
	 * Test that literal escape sequences are preserved.
	 */
	public function test_literal_escape_sequences_are_preserved() {

		$decoded = json_decode( $this->normalize( $this->get_json() ), true );

		$this->assertSame(
			'Calls {@see \\alpha()}; preserves \\xC0.',
			$decoded[1]['functions'][0]['doc']['description']
		);
		$this->assertSame( '\\x09tab', $decoded[1]['functions'][0]['hooks'][0]['name'] );
	}

	/**
	 * Test that documentation text passes through as authored.
	 *
	 * The exporter no longer rewrites documentation text, so a difference in
	 * these fields is a real behavior change that the diff must show.
	 *
	 * @dataProvider data_documentation_text
	 *
	 * @param string $key   Key holding documentation text.
	 * @param string $value Documentation text.
	 */
	public function test_documentation_text_passes_through( $key, $value ) {

		$decoded = json_decode( $this->normalize( json_encode( array( $key => $value ) ) ), true );

		$this->assertSame( $value, $decoded[ $key ] );
	}

	/**
	 * Data provider for documentation text.
	 *
	 * @return array[] Key and documentation text.
	 */
	public function data_documentation_text() {

		return array(
			'inline reference in a description'      => array( 'description', 'Calls {@see \\alpha()}.' ),
			'inline reference in a long description' => array( 'long_description', 'Calls {@link \\alpha()}.' ),
			'inline reference in tag content'        => array( 'content', 'See {@see \\Widget::render()}.' ),
			'see tag reference'                      => array( 'refers', '\\alpha()' ),
			'link tag target'                        => array( 'link', '\\alpha()' ),
		);
	}

	/**
	 * Test that printed expressions only lose an anchored global prefix.
	 *
	 * Printed expressions are normalized with the same rule the exporter applies,
	 * so that global names appearing inside string literals are left alone.
	 *
	 * @dataProvider data_printed_expressions
	 *
	 * @param string $key      Key holding the printed expression.
	 * @param string $value    Printed expression.
	 * @param string $expected Expected normalized expression.
	 */
	public function test_printed_expressions_normalize_anchored_prefixes( $key, $value, $expected ) {

		$decoded = json_decode( $this->normalize( json_encode( array( $key => $value ) ) ), true );

		$this->assertSame( $expected, $decoded[ $key ] );
	}

	/**
	 * Data provider for printed expressions.
	 *
	 * @return array[] Key, printed expression, and expected normalized expression.
	 */
	public function data_printed_expressions() {

		return array(
			'string literal containing a global name' => array( 'default', "'see \\Foo bar'", "'see \\Foo bar'" ),
			'string literal in a constant value'      => array( 'value', "'see \\Foo bar'", "'see \\Foo bar'" ),
			'printed global name'                     => array( 'default', '\\Foo', 'Foo' ),
			'printed namespaced name'                 => array( 'default', '\\Vendor\\Thing', '\\Vendor\\Thing' ),
		);
	}

	/**
	 * Test that real content changes remain visible.
	 */
	public function test_content_changes_remain_visible() {

		$changed = json_decode( $this->get_shuffled_json(), true );

		$changed[1]['functions'][0]['name'] = 'changed';

		$this->assertNotSame(
			$this->normalize( $this->get_json() ),
			$this->normalize( json_encode( $changed ) )
		);
	}
}
