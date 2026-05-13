<?php

/**
 * Normalizes generated JSON for stable `diff -u` comparisons.
 *
 * This removes incidental parser/build details and canonicalizes ordering for
 * object keys and unordered parser collections. Ordered documentation data,
 * such as function arguments and docblock tags, is left in source order.
 *
 * Example:
 *
 *     php prep-diff.php < before.json > before.norm.json
 *     php prep-diff.php < after.json > after.norm.json
 *     diff -u before.norm.json after.norm.json
 */

/**
 * Checks if an array is a JSON list.
 *
 * @param array $array Array to inspect.
 * @return bool Whether the array is a list.
 */
function wp_parser_prep_diff_is_list( array $array ) {
	if ( array() === $array ) {
		return true;
	}

	return array_keys( $array ) === range( 0, count( $array ) - 1 );
}

/**
 * Checks whether a normalized path ends with the given key sequence.
 *
 * @param array $path   Current JSON path.
 * @param array $suffix Expected key suffix.
 * @return bool Whether the path ends with the suffix.
 */
function wp_parser_prep_diff_path_ends_with( array $path, array $suffix ) {
	if ( count( $suffix ) > count( $path ) ) {
		return false;
	}

	return array_slice( $path, -count( $suffix ) ) === $suffix;
}

/**
 * Checks whether a list contains simple records that should sort by name.
 *
 * @param array $list JSON list.
 * @return bool Whether the list contains simple name records.
 */
function wp_parser_prep_diff_is_simple_name_record_list( array $list ) {
	if ( array() === $list ) {
		return false;
	}

	$allowed_keys = array( 'endLine', 'end_line', 'line', 'name', 'startLine' );

	foreach ( $list as $item ) {
		if ( ! is_array( $item ) || ! isset( $item['name'] ) || ! is_scalar( $item['name'] ) ) {
			return false;
		}

		foreach ( array_keys( $item ) as $key ) {
			if ( ! in_array( $key, $allowed_keys, true ) ) {
				return false;
			}
		}
	}

	return true;
}

/**
 * Normalizes scalar values that should not affect output comparisons.
 *
 * @param mixed       $value Scalar value.
 * @param string|null $key   Parent object key.
 * @return mixed Normalized value.
 */
function wp_parser_prep_diff_normalize_scalar( $value, $key ) {
	if ( in_array( $key, array( 'line', 'end_line', 'startLine', 'endLine' ), true ) ) {
		return 0;
	}

	if ( 'root' === $key && is_string( $value ) ) {
		return 'wordpress/';
	}

	if ( ! is_string( $value ) ) {
		return $value;
	}

	// "\wp_kses()" -> "wp_kses()".
	$without_global_namespace = preg_replace(
		'~(^|\p{Z})\\\\([A-Z_a-z\x80-\xFF][0-9A-Z_a-z\x80-\xFF]*)([:(\p{Z}]|->|$)~',
		'$1$2$3',
		$value
	);

	return null === $without_global_namespace ? $value : $without_global_namespace;
}

/**
 * Returns a canonical sort key for an item in an unordered parser collection.
 *
 * @param mixed $item Item from a JSON list.
 * @param array $path Current JSON path.
 * @return string Sort key.
 */
function wp_parser_prep_diff_sort_key( $item, array $path ) {
	if ( ! is_array( $item ) ) {
		return json_encode( $item );
	}

	if ( array() === $path ) {
		return sprintf(
			'%s/%s',
			isset( $item['root'] ) ? $item['root'] : '',
			isset( $item['path'] ) ? $item['path'] : ''
		);
	}

	if ( wp_parser_prep_diff_path_ends_with( $path, array( 'uses', 'functions' ) ) ) {
		return sprintf( '%s|%s', isset( $item['name'] ) ? $item['name'] : '', json_encode( $item ) );
	}

	if ( wp_parser_prep_diff_path_ends_with( $path, array( 'uses', 'methods' ) ) ) {
		return sprintf(
			'%s|%s|%s|%s',
			isset( $item['class'] ) ? $item['class'] : '',
			isset( $item['name'] ) ? ( is_scalar( $item['name'] ) ? $item['name'] : json_encode( $item['name'] ) ) : '',
			isset( $item['static'] ) ? (int) $item['static'] : 0,
			json_encode( $item )
		);
	}

	$key = end( $path );

	switch ( $key ) {
		case 'classes':
		case 'interfaces':
		case 'traits':
		case 'functions':
		case 'methods':
			return sprintf(
				'%s|%s|%s',
				isset( $item['namespace'] ) ? $item['namespace'] : '',
				isset( $item['name'] ) ? ( is_scalar( $item['name'] ) ? $item['name'] : json_encode( $item['name'] ) ) : '',
				json_encode( $item )
			);

		case 'properties':
		case 'constants':
			return sprintf(
				'%s|%s',
				isset( $item['name'] ) ? $item['name'] : '',
				json_encode( $item )
			);

		case 'hooks':
			return sprintf(
				'%s|%s|%s',
				isset( $item['name'] ) ? $item['name'] : '',
				isset( $item['type'] ) ? $item['type'] : '',
				json_encode( $item )
			);

		case 'includes':
			return sprintf(
				'%s|%s|%s',
				isset( $item['type'] ) ? $item['type'] : '',
				isset( $item['name'] ) ? $item['name'] : '',
				json_encode( $item )
			);
	}

	if ( isset( $item['name'] ) && is_scalar( $item['name'] ) ) {
		return sprintf( '%s|%s', $item['name'], json_encode( $item ) );
	}

	return json_encode( $item );
}

/**
 * Checks if a list at the given path is safe to sort for diff review.
 *
 * @param array $path Current JSON path.
 * @param array $list JSON list.
 * @return bool Whether the list should be sorted.
 */
function wp_parser_prep_diff_should_sort_list( array $path, array $list ) {
	if ( array() === $path ) {
		return true;
	}

	if ( wp_parser_prep_diff_is_simple_name_record_list( $list ) ) {
		return true;
	}

	if (
		wp_parser_prep_diff_path_ends_with( $path, array( 'uses', 'functions' ) ) ||
		wp_parser_prep_diff_path_ends_with( $path, array( 'uses', 'methods' ) )
	) {
		return true;
	}

	return in_array(
		end( $path ),
		array( 'classes', 'interfaces', 'traits', 'functions', 'methods', 'properties', 'constants', 'hooks', 'includes' ),
		true
	);
}

/**
 * Recursively normalizes decoded parser JSON.
 *
 * @param mixed       $value Decoded JSON value.
 * @param array       $path  Current JSON path.
 * @param string|null $key   Parent object key.
 * @return mixed Normalized value.
 */
function wp_parser_prep_diff_normalize( $value, array $path = array(), $key = null ) {
	if ( ! is_array( $value ) ) {
		return wp_parser_prep_diff_normalize_scalar( $value, $key );
	}

	if ( wp_parser_prep_diff_is_list( $value ) ) {
		foreach ( $value as $index => $item ) {
			$value[ $index ] = wp_parser_prep_diff_normalize( $item, array_merge( $path, array( '[]' ) ) );
		}

		if ( wp_parser_prep_diff_should_sort_list( $path, $value ) ) {
			usort(
				$value,
				static function( $a, $b ) use ( $path ) {
					return strcmp(
						wp_parser_prep_diff_sort_key( $a, $path ),
						wp_parser_prep_diff_sort_key( $b, $path )
					);
				}
			);
		}

		return $value;
	}

	foreach ( $value as $child_key => $child_value ) {
		$value[ $child_key ] = wp_parser_prep_diff_normalize(
			$child_value,
			array_merge( $path, array( $child_key ) ),
			$child_key
		);
	}

	ksort( $value, SORT_STRING );

	return $value;
}

$input = file_get_contents( 'php://stdin' );
$json  = json_decode( $input, true );

if ( JSON_ERROR_NONE !== json_last_error() ) {
	fwrite( STDERR, 'Invalid JSON input: ' . json_last_error_msg() . PHP_EOL );
	exit( 1 );
}

$output = json_encode(
	wp_parser_prep_diff_normalize( $json ),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

if ( false === $output ) {
	fwrite( STDERR, 'Unable to encode normalized JSON: ' . json_last_error_msg() . PHP_EOL );
	exit( 1 );
}

echo $output . PHP_EOL;
