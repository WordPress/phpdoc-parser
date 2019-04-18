<?php
namespace WP_Parser;

use ErrorException;

/**
 * Main plugin's class. Registers things and adds WP CLI command.
 */
class Plugin {

	/**
	 * @var Relationships
	 */
	public $relationships;

	public function on_load() {

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'parser', __NAMESPACE__ . '\\Command' );
		}

		$this->relationships = new Relationships;

		add_action( 'init', array( $this, 'register_post_types' ), 11 );
		add_action( 'init', array( $this, 'register_taxonomies' ), 11 );
		add_filter( 'wp_parser_get_arguments', array( $this, 'make_args_safe' ) );
		add_filter( 'wp_parser_return_type', array( $this, 'humanize_separator' ) );

		add_filter( 'post_type_link', array( $this, 'method_permalink' ), 10, 2 );
	}

	/**
	 * Register the function and class post types
	 */
	public function register_post_types() {
		$supports = [
			'comments',
			'custom-fields',
			'editor',
			'excerpt',
			'revisions',
			'title',
		];

		$post_types = [
			new Parser_Post_Type( 'wp-parser-function', 'Functions', 'function', 'functions', $supports ),
			new Parser_Post_Type( 'wp-parser-method', 'Methods', 'method', 'methods', $supports ),
			new Parser_Post_Type( 'wp-parser-class', 'Classes', 'class', 'classes', $supports ),
			new Parser_Post_Type( 'wp-parser-hook', 'Hooks', 'hook', 'hooks', $supports ),
		];

		/* @var Parser_Post_Type $post_type */
		foreach ( $post_types as $post_type ) {
			try {
				$post_type->register();
			} catch ( ErrorException $exception ) {
				// Temp solution.
				continue;
			}
		}

	}

	/**
	 * Register the file and @since taxonomies
	 */
	public function register_taxonomies() {
		$object_types = [ 'wp-parser-class', 'wp-parser-method', 'wp-parser-function', 'wp-parser-hook' ];

		$taxonomies = [
			new Parser_Taxonomy( 'wp-parser-source-file', 'Files', 'files', $object_types, false ),
			new Parser_Taxonomy( 'wp-parser-package', '@package', 'package', $object_types ),
			new Parser_Taxonomy( 'wp-parser-since', '@since', 'since', $object_types ),
			new Parser_Taxonomy( 'wp-parser-namespace', 'Namespaces', 'namespace', $object_types ),
		];

		/* @var Parser_Taxonomy $taxonomy */
		foreach ( $taxonomies as $taxonomy ) {
			try {
				$taxonomy->register();
			} catch ( ErrorException $exception ) {
				// Temp solution.
				continue;
			}
		}
	}

	/**
	 * @param string   $link
	 * @param \WP_Post $post
	 *
	 * @return string|void
	 */
	public function method_permalink( $link, $post ) {
		if ( $post->post_type !== 'wp-parser-method' || $post->post_parent === 0 ) {
			return $link;
		}

		list( $class, $method ) = explode( '-', $post->post_name );

		return home_url( user_trailingslashit( "method/$class/$method" ) );
	}

	/**
	 * Raw phpDoc could potentially introduce unsafe markup into the HTML, so we sanitise it here.
	 *
	 * @param array $args Parameter arguments to make safe
	 *
	 * @return array
	 */
	public function make_args_safe( $args ) {

		array_walk_recursive( $args, array( $this, 'sanitize_argument' ) );

		return apply_filters( 'wp_parser_make_args_safe', $args );
	}

	/**
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public function sanitize_argument( &$value ) {
		static $filters = [
			'wp_filter_kses',
			'make_clickable',
			'force_balance_tags',
			'wptexturize',
			'convert_smilies',
			'convert_chars',
			'stripslashes_deep',
		];

		foreach ( $filters as $filter ) {
			$value = call_user_func( $filter, $value );
		}

		return $value;
	}

	/**
	 * Replace separators with a more readable version
	 *
	 * @param string $type Variable type
	 *
	 * @return string
	 */
	public function humanize_separator( $type ) {
		return str_replace( '|', '<span class="wp-parser-item-type-or">' . _x( ' or ', 'separator', 'wp-parser' ) . '</span>', $type );
	}
}
