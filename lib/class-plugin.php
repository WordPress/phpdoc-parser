<?php
namespace WP_Parser;

/**
 * Main plugin's class. Registers things and adds WP CLI command.
 */
class Plugin {

	/**
	 * @var \WP_Parser\Relationships
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

		add_filter( 'post_type_link', array( $this, 'scoped_permalink' ), 10, 2 );
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

		if ( ! post_type_exists( 'wp-parser-function' ) ) {

			register_post_type(
				'wp-parser-function',
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


		if ( ! post_type_exists( 'wp-parser-method' ) ) {

			add_rewrite_rule( 'method/([^/]+)/([^/]+)/?$', 'index.php?post_type=wp-parser-method&name=$matches[1]-$matches[2]', 'top' );

			register_post_type(
				'wp-parser-method',
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


		if ( ! post_type_exists( 'wp-parser-class' ) ) {

			register_post_type(
				'wp-parser-class',
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

		if ( ! post_type_exists( 'wp-parser-hook' ) ) {

			register_post_type(
				'wp-parser-hook',
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

		$object_types = array( 'wp-parser-class', 'wp-parser-method', 'wp-parser-function', 'wp-parser-hook' );

		if ( ! taxonomy_exists( 'wp-parser-source-file' ) ) {

			register_taxonomy(
				'wp-parser-source-file',
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

		if ( ! taxonomy_exists( 'wp-parser-package' ) ) {

			register_taxonomy(
				'wp-parser-package',
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

		if ( ! taxonomy_exists( 'wp-parser-since' ) ) {

			register_taxonomy(
				'wp-parser-since',
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

		if ( ! taxonomy_exists( 'wp-parser-namespace' ) ) {

			register_taxonomy(
				'wp-parser-namespace',
				$object_types,
				array(
					'hierarchical'          => true,
					'label'                 => __( 'Namespaces', 'wp-parser' ),
					'public'                => true,
					'rewrite'               => array( 'slug' => 'namespace' ),
					'sort'                  => false,
					'update_count_callback' => '_update_post_term_count',
				)
			);
		}

		if ( ! taxonomy_exists( 'wp-parser-programming-language' ) ) {

			register_taxonomy(
				'wp-parser-programming-language',
				$object_types,
				array(
					'hierarchical'          => true,
					'label'                 => __( 'Programming language', 'wp-parser' ),
					'public'                => true,
					'rewrite'               => array( 'slug' => 'programming-language' ),
					'sort'                  => false,
					'update_count_callback' => '_update_post_term_count',
				)
			);
		}
	}

	/**
	 * Changes permalinks for doc post types to the format language/type/namespace/name.
	 * If no namespace is present the format will be language/type/name.
	 *
	 * @param string   $link
	 * @param \WP_Post $post
	 *
	 * @return string|void
	 */
	public function scoped_permalink( $link, $post ) {
		$object_types = array( 'wp-parser-class', 'wp-parser-method', 'wp-parser-function', 'wp-parser-hook' );

		if ( ! in_array( $post->post_type, $object_types, true ) ) {
			return $link;
		}

		$parts     = explode( '-', $post->post_name );
		$language  = array_shift( $parts );
		$name      = array_pop( $parts );
		$namespace = implode( '-', $parts );
		$type      = substr( $post->post_type, 10 ); // strip 'wp-parser-'.

		if ( empty( $namespace ) ) {
			$link = home_url( user_trailingslashit( "$language/$type/$name" ) );
		} else {
			$link = home_url( user_trailingslashit( "$language/$type/$namespace/$name" ) );
		}

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

		array_walk_recursive( $args, array( $this, 'sanitize_argument' ) );

		return apply_filters( 'wp_parser_make_args_safe', $args );
	}

	/**
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public function sanitize_argument( &$value ) {

		static $filters = array(
			'wp_filter_kses',
			'make_clickable',
			'force_balance_tags',
			'wptexturize',
			'convert_smilies',
			'convert_chars',
			'stripslashes_deep',
		);

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
