<?php

/**
 * Get the current function's return type
 *
 * @return string
 */
function wpfuncref_return_type() {
	$function_data = get_post_meta( get_the_ID(), '_wpapi_tags', true );
	$return_type   = wp_list_filter( $function_data, array( 'name' => 'return' ) );

	if ( ! empty( $return_type ) ) {

		// Grab the description from the return type
		$return_type = array_shift( $return_type );
		$return_type = $return_type['content'];
		$parts       = explode( ' ', $return_type );
		$return_type = $parts[0];


	} else {
		$return_type = 'void';
	}

	$return_type = wpfuncref_format_type( $return_type );

	return apply_filters( 'wpfuncref_the_return_type', $return_type );
}

/**
 * Print the current function's return type
 *
 * @see wpfuncref_return_type
 */
function wpfuncref_the_return_type() {
	echo wpfuncref_return_type();
}

/**
 * Get the current function's return description
 *
 * @return string
 */
function wpfuncref_the_return_desc() {
	$function_data = get_post_meta( get_the_ID(), '_wpapi_tags', true );
	$return_desc   = wp_list_filter( $function_data, array( 'name' => 'return' ) );

	if ( ! empty( $return_desc ) ) {

		// Grab the description from the return type
		$return_desc = array_shift( $return_desc );
		$return_desc = $return_desc['content'];
		$parts       = explode( ' ', $return_desc );

		// This handles where the parser had found something like "array Posts" when the PHPDoc looks like "@return array Posts".
		if ( count( $parts ) > 1 )
			$return_desc = ': ' . implode( ' ', array_slice( $parts, 1 ) );
		else
			$return_desc = '';


	} else {
		$return_desc = '';
	}

	return apply_filters( 'wpfuncref_the_return_desc', $return_desc );
}

/**
 * Print the current function's return description
 */
function wpfuncref_the_return_desc() {
	echo wpfuncref_the_return_desc();
}

/**
 * Do any of the current function's arguments have a default value?
 *
 * @return bool
 */
function wpfuncref_arguments_have_default_values() {
	$retval = wp_list_filter( get_post_meta( get_the_ID(), '_wpapi_args', true ), array( 'name' => 'default' ) );
	return apply_filters( 'wpfuncref_arguments_have_default_values', ! empty( $retval ) );
}

/**
 * Is the current function deprecated?
 *
 * @return bool
 */
function wpfuncref_is_function_deprecated() {
	$retval = wp_list_filter( get_post_meta( get_the_ID(), '_wpapi_tags', true ), array( 'name' => 'deprecated' ) );
	return apply_filters( 'wpfuncref_is_function_deprecated', ! empty( $retval ) );
}

/**
 * Return the current function's arguments. Template tag function for the function post type.
 *
 * The default value stuff is messy because ['arguments'] doesn't contain information from ['doc']['tags'][x]['name' == 'param'].
 *
 * @return array array( [0] => array( 'name' => '', 'type' => '', 'desc' => '' ), ... )
 * @see https://github.com/rmccue/WP-Parser/issues/4
 */
function wpfuncref_get_the_arguments() {
	$args_data     = get_post_meta( get_the_ID(), '_wpapi_args', true );
	$function_data = get_post_meta( get_the_ID(), '_wpapi_tags', true );
	$params        = wp_list_filter( $function_data, array( 'name' => 'param' ) );
	$return_args   = array();

	if ( ! empty( $params ) ) {
		foreach ( $params as $param ) {

			// Split the string: "[bool] [$launch_missles] Fire the rockets"
			$parts = explode( ' ', $param['content'] );

			$return_desc = '';
			$return_type = $parts[0];

			// The substr handles where the parser had found something like "array Posts" when the PHPDoc looks like "@return array Posts".
			if ( count( $parts ) > 2 )
				$return_desc = implode( ' ', array_slice( $parts, 2 ) );

			// Assemble data for this parameter
			$arg = array(
				'desc' => $return_desc,
				'name' => $parts[1],
				'type' => $return_type,
			);

			// Maybe add default value
			$param_default_value = wp_list_filter( $args_data, array( 'name' => $parts[1] ) );
			if ( ! empty( $param_default_value ) ) {
				$param_default_value = array_shift( $param_default_value );
				$param_default_value = $param_default_value['default'];

				if ( ! is_null( $param_default_value ) )
					$arg['default_value'] = $param_default_value;
			}

			$return_args[] = $arg;
		}


	} else {
		$return_args = array();
	}

	return apply_filters( 'wpfuncref_get_the_arguments', $return_args );
}


/**
 * Raw phpDoc could potentially introduce unsafe markup into the HTML, so we sanitise it here.
 *
 * @param array $args Parameter arguments to make safe
 * @param array Filtered arguments
 * @return array
 */
function wpfuncref_make_args_safe( $args ) {
	$filters = array(
		'wp_filter_kses',
		'make_clickable',
		'force_balance_tags',
		'wptexturize',
		'convert_smilies',
		'convert_chars',
		'wpautop',
		'stripslashes_deep',
	);

	foreach ( $args as &$arg ) {
		foreach ( $arg as &$value ) {

			// Loop through all elements of the $args array, and apply our set of filters to them.
			foreach ( $filters as $filter_function )
				$value = call_user_func( $filter_function, $value );
		}
	}

	return apply_filters( 'wpfuncref_make_args_safe', $args );
}
add_filter( 'wpfuncref_get_the_arguments', 'wpfuncref_make_args_safe' );

function wpfuncref_format_type( $type ) {
	return str_replace( '|', ' <span class="wpapi-item-type-or">or</span> ', $type );
}

/**
 * Returns the URL to the current function on the bbP/BP trac.
 *
 * @return string
 */
function bpcodex_get_wpapi_source_link() {
	if ( strpos( wp_get_theme()->get( 'Name' ), 'BuddyPress.org' ) !== false )
		$trac_url = 'https://buddypress.trac.wordpress.org/browser/trunk/';
	else
		$trac_url = 'https://bbpress.trac.wordpress.org/browser/trunk/';

	// Find the current post in the wpapi-source-file term
	$term = get_the_terms( get_the_ID(), 'wpapi-source-file' );
	if ( ! empty( $term ) && ! is_wp_error( $term ) ) {
		$term      = array_shift( $term );
		$line_num  = (int) get_post_meta( get_the_ID(), '_wpapi_line_num', true );

		// Link to the specific file name and the line number on trac
		$trac_url .= "{$term->name}#L{$line_num}";
	}

	return $trac_url;
}