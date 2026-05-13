<?php

$script = dirname( __DIR__ ) . '/prep-diff.php';

function normalize_with_prep_diff( $script, $json ) {
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

	if ( ! is_resource( $process ) ) {
		throw new RuntimeException( 'Unable to start prep-diff.php.' );
	}

	fwrite( $pipes[0], $json );
	fclose( $pipes[0] );

	$output = stream_get_contents( $pipes[1] );
	$error  = stream_get_contents( $pipes[2] );

	fclose( $pipes[1] );
	fclose( $pipes[2] );

	$status = proc_close( $process );

	if ( 0 !== $status ) {
		throw new RuntimeException( trim( $error ) );
	}

	return $output;
}

function assert_true( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

$a = json_encode(
	array(
		array(
			'root'      => '/tmp/build-a',
			'path'      => 'beta.php',
			'call_graph' => array(
				array( 'name' => 'zeta', 'line' => 9, 'end_line' => 9 ),
				array( 'name' => 'alpha', 'line' => 3, 'end_line' => 3 ),
			),
			'functions' => array(
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
						array( 'name' => '$first', 'type' => '' ),
						array( 'name' => '$second', 'type' => '' ),
					),
					'doc'       => array(
						'tags'             => array(
							array( 'name' => 'since', 'content' => '1.0.0' ),
							array( 'name' => 'param', 'content' => 'First.', 'variable' => '$first' ),
						),
						'long_description' => '',
						'description'      => 'Calls \\alpha().',
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

$b = json_encode(
	array(
		array(
			'root' => '/tmp/build-b',
			'path' => 'alpha.php',
		),
		array(
			'path'      => 'beta.php',
			'root'      => '/tmp/build-b',
			'call_graph' => array(
				array( 'end_line' => 90, 'line' => 90, 'name' => 'zeta' ),
				array( 'end_line' => 30, 'line' => 30, 'name' => 'alpha' ),
			),
			'functions' => array(
				array(
					'namespace' => 'global',
					'name'      => 'beta',
					'line'      => 98,
					'doc'       => array(
						'description'      => 'Calls alpha().',
						'long_description' => '',
						'tags'             => array(
							array( 'name' => 'since', 'content' => '1.0.0' ),
							array( 'name' => 'param', 'content' => 'First.', 'variable' => '$first' ),
						),
					),
					'arguments' => array(
						array( 'type' => '', 'name' => '$first' ),
						array( 'type' => '', 'name' => '$second' ),
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

$a_normalized = normalize_with_prep_diff( $script, $a );
$b_normalized = normalize_with_prep_diff( $script, $b );

assert_true( $a_normalized === $b_normalized, 'Equivalent shuffled JSON should normalize identically.' );

$decoded = json_decode( $a_normalized, true );

assert_true( 'alpha.php' === $decoded[0]['path'], 'Top-level files should sort by path.' );
assert_true( array( 'alpha', 'zeta' ) === array_column( $decoded[1]['call_graph'], 'name' ), 'Simple name records should sort by name.' );
assert_true( array( '$first', '$second' ) === array_column( $decoded[1]['functions'][0]['arguments'], 'name' ), 'Function argument order should be preserved.' );
assert_true( array( 'since', 'param' ) === array_column( $decoded[1]['functions'][0]['doc']['tags'], 'name' ), 'Doc tag order should be preserved.' );
assert_true( array( 'alpha', 'zeta' ) === array_column( $decoded[1]['functions'][0]['uses']['functions'], 'name' ), 'Function uses should sort by name.' );
assert_true( array_keys( $decoded[1]['functions'][0] ) === array( 'arguments', 'doc', 'line', 'name', 'namespace', 'uses' ), 'Object keys should be sorted.' );

$changed = json_decode( $b, true );
$changed[1]['functions'][0]['name'] = 'changed';

assert_true(
	$a_normalized !== normalize_with_prep_diff( $script, json_encode( $changed ) ),
	'Real content changes should remain visible.'
);

echo "prep-diff tests passed\n";
