<?php
/**
 * Plugin Name: WP Parser
 * Description: Create a function reference site powered by WordPress
 * Author: Ryan McCue and Paul Gibbs
 * Plugin URI: https://github.com/rmccue/WP-Parser
 * Version:
 */

namespace WP_Parser;

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'funcref', __NAMESPACE__ . '\\Command' );
}

add_action( 'init', __NAMESPACE__ . '\\register_post_types' );
add_action( 'init', __NAMESPACE__ . '\\register_taxonomies' );
add_filter( 'wpfuncref_get_the_arguments', __NAMESPACE__ . '\\make_args_safe' );
add_filter( 'wpfuncref_the_return_type', __NAMESPACE__ . '\\humanize_separator' );

add_filter( 'the_content', __NAMESPACE__ . '\\expand_content' );
add_filter( 'the_content', __NAMESPACE__ . '\\autop_for_non_funcref' );
remove_filter( 'the_content', 'wpautop' );

/**
 * Register the function and class post types
 */
function register_post_types() {

	$supports = array(
		'comments',
		'custom-fields',
		'editor',
		'excerpt',
		'revisions',
		'title',
	);

	// Functions
	register_post_type(
		'wpapi-function',
		array(
			'has_archive' => 'functions',
			'label'       => __( 'Functions', 'wp-parser' ),
			'public'      => true,
			'rewrite'     => array(
				'feeds'      => false,
				'slug'       => 'function',
				'with_front' => false,
			),
			'supports'    => $supports,
		)
	);

	// Methods
	add_rewrite_rule( 'method/([^/]+)/([^/]+)/?$', 'index.php?post_type=wpapi-function&name=$matches[1]-$matches[2]', 'top' );

	// Classes
	register_post_type(
		'wpapi-class',
		array(
			'has_archive' => 'classes',
			'label'       => __( 'Classes', 'wp-parser' ),
			'public'      => true,
			'rewrite'     => array(
				'feeds'      => false,
				'slug'       => 'class',
				'with_front' => false,
			),
			'supports'    => $supports,
		)
	);

	// Hooks
	register_post_type(
		'wpapi-hook',
		array(
			'has_archive' => 'hooks',
			'label'       => __( 'Hooks', 'wp-parser' ),
			'public'      => true,
			'rewrite'     => array(
				'feeds'      => false,
				'slug'       => 'hook',
				'with_front' => false,
			),
			'supports'    => $supports,
		)
	);
}

/**
 * Register the file and @since taxonomies
 */
function register_taxonomies() {
	// Files
	register_taxonomy(
		'wpapi-source-file',
		array( 'wpapi-class', 'wpapi-function', 'wpapi-hook' ),
		array(
			'label'                 => __( 'Files', 'wp-parser' ),
			'public'                => true,
			'rewrite'               => array( 'slug' => 'files' ),
			'sort'                  => false,
			'update_count_callback' => '_update_post_term_count',
		)
	);

	// Package
	register_taxonomy(
		'wpapi-package',
		array( 'wpapi-class', 'wpapi-function', 'wpapi-hook' ),
		array(
			'hierarchical'          => true,
			'label'                 => '@package',
			'public'                => true,
			'rewrite'               => array( 'slug' => 'package' ),
			'sort'                  => false,
			'update_count_callback' => '_update_post_term_count',
		)
	);

	// @since
	register_taxonomy(
		'wpapi-since',
		array( 'wpapi-class', 'wpapi-function', 'wpapi-hook' ),
		array(
			'hierarchical'          => true,
			'label'                 => __( '@since', 'wp-parser' ),
			'public'                => true,
			'rewrite'               => array( 'slug' => 'since' ),
			'sort'                  => false,
			'update_count_callback' => '_update_post_term_count',
		)
	);
}

function method_permalink( $link, $post ) {

	if ( $post->post_type !== 'wpapi-function' || $post->post_parent == 0 ) {
		return $link;
	}

	list( $class, $method ) = explode( '-', $post->post_name );
	$link = home_url( user_trailingslashit( "method/$class/$method" ) );

	return $link;
}

add_filter( 'post_type_link', __NAMESPACE__ . '\\method_permalink', 10, 2 );

/**
 * Raw phpDoc could potentially introduce unsafe markup into the HTML, so we sanitise it here.
 *
 * @param array $args Parameter arguments to make safe
 *
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
		'stripslashes_deep',
	);

	foreach ( $args as &$arg ) {
		foreach ( $arg as &$value ) {
			foreach ( $filters as $filter_function ) {
				if ( is_array( $value ) ) {
					foreach ( $value as &$v ) {
						$v = call_user_func( $filter_function, $v );
					}
				} else {
					$value = call_user_func( $filter_function, $value );
				}
			}
		}
	}

	return apply_filters( 'wpfuncref_make_args_safe', $args );
}

/**
 * Replace separators with a more readable version
 *
 * @param string $type Variable type
 *
 * @return string
 */
function humanize_separator( $type ) {
	return str_replace( '|', '<span class="wpapi-item-type-or">' . _x( ' or ', 'separator', 'wp-parser' ) . '</span>', $type );
}

/**
 * Extend the post's content with function reference pieces
 *
 * @param string $content Unfiltered content
 *
 * @return string Content with Function reference pieces added
 */
function expand_content( $content ) {
	$post = get_post();

	if ( $post->post_type !== 'wpapi-class' && $post->post_type !== 'wpapi-function' && $post->post_type !== 'wpapi-hook' ) {
		return $content;
	}

	$before_content = get_prototype();

	$before_content .= '<p class="wpfuncref-description">' . get_the_excerpt() . '</p>';
	$before_content .= '<div class="wpfuncref-longdesc">';

	$after_content = '</div>';

	$after_content .= '<div class="wpfuncref-arguments"><h3>Arguments</h3>';
	$args = get_arguments();

	foreach ( $args as $arg ) {
		$after_content .= '<div class="wpfuncref-arg">';
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

	$before_content = apply_filters( 'wpfuncref_before_content', $before_content );
	$after_content  = apply_filters( 'wpfuncref_after_content', $after_content );

	return $before_content . $content . $after_content;
}

/**
 * Re-enable autopee for the non-funcref posts
 *
 * We can't selectively filter the_content for wpautop, so we remove it and
 * readd this to check instead.
 *
 * @param string $content Unfiltered content
 *
 * @return string Autopeed content
 */
function autop_for_non_funcref( $content ) {
	$post = get_post();

	if ( $post->post_type !== 'wpapi-class' && $post->post_type !== 'wpapi-function' && $post->post_type !== 'wpapi-hook' ) {
		$content = wpautop( $content );
	}

	return $content;
}
