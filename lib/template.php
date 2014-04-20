<?php

namespace WP_Parser;

/**
 * Parser post's content with function reference pieces.
 */
function the_content() {

	$post    = get_post();
	$content = get_the_content();

	if ( $post->post_type !== 'wpapi-class' && $post->post_type !== 'wpapi-method' && $post->post_type !== 'wpapi-function' && $post->post_type !== 'wpapi-hook' ) {
		return $content;
	}

	if ( 'wpapi-hook' === $post->post_type ) {
		$before_content = get_hook_prototype();
	} else {
		$before_content = get_prototype();
	}

	$before_content .= '<p class="wp-parser-description">' . get_the_excerpt() . '</p>';
	$before_content .= '<div class="wp-parser-longdesc">';

	$after_content = '</div>';

	$after_content .= '<div class="wp-parser-arguments"><h3>Arguments</h3>';

	if ( 'wpapi-hook' === $post->post_type ) {
		$args = get_hook_arguments();
	} else {
		$args = get_arguments();
	}

	foreach ( $args as $arg ) {
		$after_content .= '<div class="wp-parser-arg">';
		$after_content .= '<h4><code><span class="type">' . implode( '|', $arg['types'] ) . '</span> <span class="variable">' . $arg['name'] . '</span></code></h4>';
		if ( ! empty( $arg['desc'] ) ) {
			$after_content .= wpautop( $arg['desc'], false );
		}
		$after_content .= '</div>';
	}

	$after_content .= '</div>';

	$source = get_source_link();

	if ( $source ) {
		$after_content .= '<a href="' . $source . '">Source</a>';
	}

	$before_content = apply_filters( 'wp_parser_before_content', $before_content );
	$after_content  = apply_filters( 'wp_parser_after_content', $after_content );

	echo $before_content . $content . $after_content;
}

/**
 * Get the current function's return types
 *
 * @return array
 */
function get_return_type() {
	$function_data = get_post_meta( get_the_ID(), '_wpapi_tags', true );
	$return_type   = wp_list_filter( $function_data, array( 'name' => 'return' ) );

	if ( ! empty( $return_type ) ) {
		// Grab the description from the return type
		$return_type = array_shift( $return_type );
		$return_type = $return_type['types'];
	} else {
		$return_type = array( 'void' );
	}

	return apply_filters( 'wp_parser_return_type', $return_type );
}

/**
 * Print the current function's return type
 *
 * @see return_type
 */
function the_return_type() {
	echo implode( '|', get_return_type() );
}

/**
 * Get the current function's return description
 *
 * @return string
 */
function get_return_desc() {
	$function_data = get_post_meta( get_the_ID(), '_wpapi_tags', true );
	$return_desc   = wp_list_filter( $function_data, array( 'name' => 'return' ) );

	if ( ! empty( $return_desc ) ) {
		// Grab the description from the return type
		$return_desc = array_shift( $return_desc );
		$return_desc = $return_desc['content'];
	} else {
		$return_desc = '';
	}

	return apply_filters( 'wp_parser_return_desc', $return_desc );
}

/**
 * Print the current function's return description
 */
function the_return_desc() {
	echo get_return_desc();
}

/**
 * Do any of the current function's arguments have a default value?
 *
 * @return bool
 */
function arguments_have_default_values() {
	$retval = wp_list_filter( get_post_meta( get_the_ID(), '_wpapi_args', true ), array( 'name' => 'default' ) );

	return apply_filters( 'wp_parser_arguments_have_default_values', ! empty( $retval ) );
}

/**
 * Is the current function deprecated?
 *
 * @return bool
 */
function is_function_deprecated() {
	$retval = wp_list_filter( get_post_meta( get_the_ID(), '_wpapi_tags', true ), array( 'name' => 'deprecated' ) );

	return apply_filters( 'wp_parser_is_function_deprecated', ! empty( $retval ) );
}

/**
 * Return the current function's arguments.
 *
 * @return array array( [0] => array( 'name' => '', 'type' => '', 'desc' => '' ), ... )
 */
function get_arguments() {
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

	return apply_filters( 'wp_parser_get_arguments', $return_args );
}

/**
 * Return the current hook's arguments.
 *
 * @return array array( [0] => array( 'name' => '', 'type' => '', 'desc' => '' ), ... )
 */
function get_hook_arguments() {
	$args_data = get_post_meta( get_the_ID(), '_wpapi_args', true );
	$hook_data = get_post_meta( get_the_ID(), '_wpapi_tags', true );
	$params    = wp_list_filter( $hook_data, array( 'name' => 'param' ) );

	$return_args = array();

	if ( ! empty( $args_data ) ) {
		foreach ( $args_data as $arg ) {
			$param_tag = array_shift( $params );

			$param = array();

			if ( ! empty( $param_tag['variable'] ) ) {
				$param['name'] = $param_tag['variable'];
			} elseif ( strpos( $arg, '$' ) === 0 ) {
				$param['name'] = $arg;
			} else {
				$param['name'] = '$(unnamed)';
			}

			if ( ! empty( $param_tag['types'] ) ) {
				$param['types'] = $param_tag['types'];
			} else {
				$param['types'] = array();
			}

			if ( ! empty( $param_tag['content'] ) ) {
				$param['desc'] = $param_tag['content'];
			}

			$param['value'] = $arg;

			$return_args[] = $param;
		}
	}

	return apply_filters( 'wp_parser_get_hook_arguments', $return_args );
}

/**
 * Retrieve the function's prototype as HTML
 *
 * Use the wp_parser_prototype filter to change the content of this.
 *
 * @return string Prototype HTML
 */
function get_prototype() {
	$type = get_return_type();

	$friendly_args = array();
	$args          = get_arguments();
	foreach ( $args as $arg ) {
		$friendly = sprintf( '<span class="type">%s</span> <span class="variable">%s</span>', implode( '|', $arg['types'] ), $arg['name'] );
		if ( ! empty( $arg['default_value'] ) ) {
			$friendly .= ' <span class="default"> = <span class="value">' . $arg['default_value'] . '</span></span>';
		}

		$friendly_args[] = $friendly;
	}
	$friendly_args = implode( ', ', $friendly_args );

	$name = get_the_title();

	$prototype = sprintf( '<p class="wp-parser-prototype"><code><span class="type">%s</span> %s ( %s )</code></p>', implode( '|', $type ), $name, $friendly_args );

	return apply_filters( 'wp_parser_prototype', $prototype );
}

/**
 * Print the function's prototype
 *
 * @see get_prototype
 */
function the_prototype() {
	echo get_prototype();
}

/**
 * Retrieve the hook's prototype as HTML.
 *
 * Use the wp_parser_hook_prototype filter to change the content of this.
 *
 * @return string Prototype HTML
 */
function get_hook_prototype() {
	$friendly_args = array();
	$args          = get_hook_arguments();
	foreach ( $args as $arg ) {
		$friendly = sprintf( '<span class="type">%s</span> <span class="variable">%s</span>', implode( '|', $arg['types'] ), $arg['name'] );
		if ( ! empty( $arg['value'] ) && strpos( $arg['value'], '$' ) !== 0 ) {
			$friendly .= ' <span class="default"> = <span class="value">' . $arg['value'] . '</span></span>';
		}

		$friendly_args[] = $friendly;
	}
	$friendly_args = implode( ', ', $friendly_args );

	$name = get_the_title();

	$prototype = sprintf( '<p class="wp-parser-prototype"><code> %s ( %s )</code></p>', $name, $friendly_args );

	return apply_filters( 'wp_parser_hook_prototype', $prototype );
}

/**
 * Returns the URL to the current function on the bbP/BP trac.
 *
 * @return string
 */
function get_source_link() {
	$trac_url = apply_filters( 'wp_parser_source_link_base', false );
	if ( empty( $trac_url ) ) {
		return '';
	}

	// Find the current post in the wpapi-source-file term
	$term = get_the_terms( get_the_ID(), 'wpapi-source-file' );
	if ( ! empty( $term ) && ! is_wp_error( $term ) ) {
		$term     = array_shift( $term );
		$line_num = (int) get_post_meta( get_the_ID(), '_wpapi_line_num', true );

		// The format here takes the base URL, the term name, and the line number
		$format = apply_filters( 'wp_parser_source_link_format', '%s%s#L%d' );
		// Link to the specific file name and the line number on trac
		$trac_url = sprintf( $format, $trac_url, $term->name, $line_num );
	}

	return $trac_url;
}
