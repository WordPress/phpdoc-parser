<?php

/**
 * Get the current function's return types
 *
 * @return array
 */
function wpfuncref_return_type() {
	$function_data = get_post_meta( get_the_ID(), '_wpapi_tags', true );
	$return_type   = wp_list_filter( $function_data, array( 'name' => 'return' ) );

	if ( ! empty( $return_type ) ) {
		// Grab the description from the return type
		$return_type = array_shift( $return_type );
		$return_type = $return_type['types'];
	} else {
		$return_type = array( 'void' );
	}

	return apply_filters( 'wpfuncref_return_type', $return_type );
}

/**
 * Print the current function's return type
 *
 * @see wpfuncref_return_type
 */
function wpfuncref_the_return_type() {
	echo implode( '|', wpfuncref_return_type() );
}

/**
 * Get the current function's return description
 *
 * @return string
 */
function wpfuncref_return_desc() {
	$function_data = get_post_meta( get_the_ID(), '_wpapi_tags', true );
	$return_desc   = wp_list_filter( $function_data, array( 'name' => 'return' ) );

	if ( ! empty( $return_desc ) ) {
		// Grab the description from the return type
		$return_desc = array_shift( $return_desc );
		$return_desc = $return_desc['content'];
	} else {
		$return_desc = '';
	}

	return apply_filters( 'wpfuncref_return_desc', $return_desc );
}

/**
 * Print the current function's return description
 */
function wpfuncref_the_return_desc() {
	echo wpfuncref_return_desc();
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
 * Return the current function's arguments.
 *
 * @return array array( [0] => array( 'name' => '', 'type' => '', 'desc' => '' ), ... )
 */
function wpfuncref_get_the_arguments() {
	$args_data     = get_post_meta( get_the_ID(), '_wpapi_args', true );
	$function_data = get_post_meta( get_the_ID(), '_wpapi_tags', true );
	$params        = wp_list_filter( $function_data, array( 'name' => 'param' ) );

	$return_args = array();

	if ( ! empty( $args_data ) ) {
		foreach ( $args_data as $arg ) {
			$param_tag = wp_list_filter( $params, array( 'variable' => $arg['name'] ) );
			$param_tag = array_shift( $param_tag );

			$param = array(
				'name' => $arg['name'],
			);

			if ( ! empty( $arg['default'] ) ) {
				$param['default_value'] = $arg['default'];
			}

			if ( ! empty( $arg['type'] ) ) {
				$param['types'] = array( $arg['type'] );
			} else if ( ! empty( $param_tag['types'] ) ) {
				$param['types'] = $param_tag['types'];
			} else {
				$param['types'] = array();
			}

			if ( ! empty( $param_tag['content'] ) ) {
				$param['desc'] = $param_tag['content'];
			}

			$return_args[] = $param;
		}
	}

	return apply_filters( 'wpfuncref_get_the_arguments', $return_args );
}

/**
 * Retrieve the function's prototype as HTML
 *
 * Use the wpfuncref_prototype filter to change the content of this.
 *
 * @return string Prototype HTML
 */
function wpfuncref_prototype() {
	$type = wpfuncref_return_type();

	$friendly_args = array();
	$args          = wpfuncref_get_the_arguments();
	foreach ( $args as $arg ) {
		$friendly = sprintf( '<span class="type">%s</span> <span class="variable">%s</span>', implode( '|', $arg['types'] ), $arg['name'] );
		if ( ! empty( $arg['default_value'] ) ) {
			$friendly .= ' <span class="default"> = <span class="value">' . $arg['default_value'] . '</span></span>';
		}

		$friendly_args[] = $friendly;
	}
	$friendly_args = implode( ', ', $friendly_args );

	$name = get_the_title();

	$prototype = sprintf( '<p class="wpfuncref-prototype"><code><span class="type">%s</span> %s ( %s )</code></p>', implode( '|', $type ), $name, $friendly_args );

	return apply_filters( 'wpfuncref_prototype', $prototype );
}

/**
 * Print the function's prototype
 *
 * @see wpfuncref_prototype
 */
function wpfuncref_the_prototype() {
	echo wpfuncref_prototype();
}


/**
 * Returns the URL to the current function on the bbP/BP trac.
 *
 * @return string
 */
function wpfuncref_source_link() {
	$trac_url = apply_filters( 'wpfuncref_source_link_base', false );
	if ( empty( $trac_url ) ) {
		return '';
	}

	// Find the current post in the wpapi-source-file term
	$term = get_the_terms( get_the_ID(), 'wpapi-source-file' );
	if ( ! empty( $term ) && ! is_wp_error( $term ) ) {
		$term     = array_shift( $term );
		$line_num = (int) get_post_meta( get_the_ID(), '_wpapi_line_num', true );

		// The format here takes the base URL, the term name, and the line number
		$format = apply_filters( 'wpfuncref_source_link_format', '%s%s#L%d' );
		// Link to the specific file name and the line number on trac
		$trac_url = sprintf( $format, $trac_url, $term->name, $line_num );
	}

	return $trac_url;
}
