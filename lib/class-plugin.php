<?php
namespace WP_Parser;

class Plugin {

	public function on_load() {

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'parser', __NAMESPACE__ . '\\Command' );
		}

		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_filter( 'wp_parser_get_arguments', array( $this, 'make_args_safe' ) );
		add_filter( 'wp_parser_return_type', array( $this, 'humanize_separator' ) );

		add_filter( 'the_content', array( $this, 'expand_content' ) );
		add_filter( 'the_content', array( $this, 'autop_for_non_funcref' ) );
		remove_filter( 'the_content', 'wpautop' );
		add_filter( 'post_type_link', array( $this, 'method_permalink' ), 10, 2 );
	}

	/**
	 * Register the function and class post types
	 */
	public function register_post_types() {

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
	public function register_taxonomies() {
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

	public function method_permalink( $link, $post ) {

		if ( $post->post_type !== 'wpapi-function' || $post->post_parent == 0 ) {
			return $link;
		}

		list( $class, $method ) = explode( '-', $post->post_name );
		$link = home_url( user_trailingslashit( "method/$class/$method" ) );

		return $link;
	}

	/**
	 * Raw phpDoc could potentially introduce unsafe markup into the HTML, so we sanitise it here.
	 *
	 * @param array $args Parameter arguments to make safe
	 *
	 * @return array
	 */
	public function make_args_safe( $args ) {

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

		return apply_filters( 'wp_parser_make_args_safe', $args );
	}

	/**
	 * Replace separators with a more readable version
	 *
	 * @param string $type Variable type
	 *
	 * @return string
	 */
	public function humanize_separator( $type ) {
		return str_replace( '|', '<span class="wpapi-item-type-or">' . _x( ' or ', 'separator', 'wp-parser' ) . '</span>', $type );
	}

	/**
	 * Extend the post's content with function reference pieces
	 *
	 * @param string $content Unfiltered content
	 *
	 * @return string Content with Function reference pieces added
	 */
	public function expand_content( $content ) {
		$post = get_post();

		if ( $post->post_type !== 'wpapi-class' && $post->post_type !== 'wpapi-function' && $post->post_type !== 'wpapi-hook' ) {
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
	public function autop_for_non_funcref( $content ) {
		$post = get_post();

		if ( $post->post_type !== 'wpapi-class' && $post->post_type !== 'wpapi-function' && $post->post_type !== 'wpapi-hook' ) {
			$content = wpautop( $content );
		}

		return $content;
	}
}
