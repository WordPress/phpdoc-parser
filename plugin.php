<?php
/**
 * Plugin Name: WP Parser
 * Description: Create a function reference site powered by WordPress
 * Author: Ryan McCue and Paul Gibbs
 * Plugin URI: https://github.com/rmccue/WP-Parser
 * Version: 1.0
 */

namespace WPFuncRef;

require __DIR__ . '/importer.php';
require __DIR__ . '/template.php';

if ( defined('WP_CLI') && WP_CLI ) {
	require __DIR__ . '/cli.php';
}

add_action( 'init', __NAMESPACE__ . '\\register_post_types' );
add_action( 'init', __NAMESPACE__ . '\\register_taxonomies' );
add_filter( 'wpfuncref_get_the_arguments', __NAMESPACE__ . '\\make_args_safe' );
add_filter( 'wpfuncref_the_return_type', __NAMESPACE__ . '\\humanize_separator' );

/**
 * Register the function and class post types
 */
function register_post_types() {
	// Functions
	register_post_type( 'wpapi-function', array(
		'has_archive'  => true,
		'hierarchical' => true,
		'label'        => __( 'Functions' ),
		'public'       => true,
		'rewrite'      => array( 'slug' => 'functions' ),
		'supports'     => array( 'comments', 'custom-fields', 'editor', 'excerpt', 'page-attributes', 'revisions', 'title' ),
		'taxonomies'   => array( 'wpapi-source-file' ),
	) );

	// Classes
	register_post_type( 'wpapi-class', array(
		'has_archive'  => true,
		'hierarchical' => false,
		'label'        => __( 'Classes' ),
		'public'       => true,
		'rewrite'      => array( 'slug' => 'classes' ),
		'supports'     => array( 'comments', 'custom-fields', 'editor', 'excerpt', 'revisions', 'title' ),
		'taxonomies'   => array( 'wpapi-source-file' ),
	) );
}

/**
 * Register the file and @since taxonomies
 *
 * @return [type] [description]
 */
function register_taxonomies() {
	// Files
	register_taxonomy( 'wpapi-source-file', array( 'wpapi-class', 'wpapi-function' ), array(
		'hierarchical'          => true,
		'label'                 => __( 'Files' ),
		'public'                => true,
		'rewrite'               => array( 'slug' => 'files' ),
		'sort'                  => false,
		'update_count_callback' => '_update_post_term_count',
	) );

	// Files
	register_taxonomy( 'wpapi-since', array( 'wpapi-class', 'wpapi-function' ), array(
		'hierarchical'          => true,
		'label'                 => __( '@since' ),
		'public'                => true,
		'sort'                  => false,
		'update_count_callback' => '_update_post_term_count',
	) );
}

/**
 * Raw phpDoc could potentially introduce unsafe markup into the HTML, so we sanitise it here.
 *
 * @param array $args Parameter arguments to make safe
 * @param array Filtered arguments
 * @return array
 */
function make_args_safe( $args ) {
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

/**
 * Replace separators with a more readable version
 *
 * @param string $type Variable type
 * @return string
 */
function humanize_separator( $type ) {
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
