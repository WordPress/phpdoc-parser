<?php

/**
 * Normalizes the generated JSON files for comparison across versions, removing
 * incidental details like line numbers, global namespace prefixes, and the
 * root directory.
 *
 * Use this script for comparing how different builds of the parser generate their
 * output. This will help minimize the difference between two JSON outputs.
 *
 * Example:
 *
 *     cat before.json | php prep-diff.php > before.norm.json
 *     cat after.json | php prep-diff.php > after.norm.json
 *     diff -u before.norm.json after.norm.json
 */

$output = json_decode( file_get_contents( 'php://stdin' ), true );

array_walk_recursive(
	$output,
	static function( &$value, $key ) {
		if ( 'line' === $key || 'end_line' === $key ) {
			$value = 0;
			return;
		}

		if ( 'root' === $key && is_string( $value ) ) {
			$value = 'wordpress/';
			return;
		}

		if ( is_string( $value ) ) {
			// "\wp_kses()" -> "wp_kses()"
			$without_global_namespace = preg_replace(
				'~(^|\p{Z})\\\\([A-Z_a-z\x80-\xFF][0-9A-Z_a-z\x80-\xFF]*)([:(\p{Z}]|->|$)~',
				'$1$2$3',
				$value,
			);

			if ( $value !== $without_global_namespace ) {
				$value = $without_global_namespace;
			}
		}
	}
);

echo json_encode( $output, JSON_PRETTY_PRINT );