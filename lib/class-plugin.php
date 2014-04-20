<?php
namespace WP_Parser;

class Plugin {

	public function on_load() {

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'parser', __NAMESPACE__ . '\\Command' );
		}

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

		$supports = array(
			'comments',
			'custom-fields',
			'editor',
			'excerpt',
			'revisions',
			'title',
		);

		if ( ! post_type_exists( 'wpapi-function' ) ) {

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
		}


		if ( ! post_type_exists( 'wpapi-method' ) ) {

			add_rewrite_rule( 'method/([^/]+)/([^/]+)/?$', 'index.php?post_type=wpapi-method&name=$matches[1]-$matches[2]', 'top' );

			register_post_type(
				'wpapi-method',
				array(
					'has_archive' => 'methods',
					'label'       => __( 'Methods', 'wp-parser' ),
					'public'      => true,
					'rewrite'     => array(
						'feeds'      => false,
						'slug'       => 'method',
						'with_front' => false,
					),
					'supports'    => $supports,
				)
			);
		}


		if ( ! post_type_exists( 'wpapi-class' ) ) {

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
		}

		if ( ! post_type_exists( 'wpapi-hook' ) ) {

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
	}

	/**
	 * Register the file and @since taxonomies
	 */
	public function register_taxonomies() {

		$object_types = array( 'wpapi-class', 'wpapi-method', 'wpapi-function', 'wpapi-hook' );

		if ( ! taxonomy_exists( 'wpapi-source-file' ) ) {

			register_taxonomy(
				'wpapi-source-file',
				$object_types,
				array(
					'label'                 => __( 'Files', 'wp-parser' ),
					'public'                => true,
					'rewrite'               => array( 'slug' => 'files' ),
					'sort'                  => false,
					'update_count_callback' => '_update_post_term_count',
				)
			);
		}

		if ( ! taxonomy_exists( 'wpapi-package' ) ) {

			register_taxonomy(
				'wpapi-package',
				$object_types,
				array(
					'hierarchical'          => true,
					'label'                 => '@package',
					'public'                => true,
					'rewrite'               => array( 'slug' => 'package' ),
					'sort'                  => false,
					'update_count_callback' => '_update_post_term_count',
				)
			);
		}

		if ( ! taxonomy_exists( 'wpapi-since' ) ) {

			register_taxonomy(
				'wpapi-since',
				$object_types,
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
	}

	public function method_permalink( $link, $post ) {

		if ( $post->post_type !== 'wpapi-method' || $post->post_parent == 0 ) {
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
}
