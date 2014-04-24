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

		$supports = array( 'custom-fields', 'editor', 'excerpt', 'revisions', 'title', );

		// Function post type.
		$function_args = array(
			'has_archive' => 'functions',
			'label'       => __( 'Functions', 'wp-parser' ),
			'labels'      => array(
				'name'               => __( 'Functions', 'wp-parser' ),
				'singular_name'      => __( 'Function', 'wp-parser' ),
				'all_items'          => __( 'Functions', 'wp-parser' ),
				'new_item'           => __( 'New Function', 'wp-parser' ),
				'add_new'            => __( 'Add New', 'wp-parser' ),
				'add_new_item'       => __( 'Add New Function', 'wp-parser' ),
				'edit_item'          => __( 'Edit Function', 'wp-parser' ),
				'view_item'          => __( 'View Function', 'wp-parser' ),
				'search_items'       => __( 'Search Functions', 'wp-parser' ),
				'not_found'          => __( 'No Functions found', 'wp-parser' ),
				'not_found_in_trash' => __( 'No Functions found in trash', 'wp-parser' ),
				'parent_item_colon'  => __( 'Parent Function', 'wp-parser' ),
				'menu_name'          => __( 'Functions', 'wp-parser' ),
			),
			'public'      => true,
			'rewrite'     => array(
				'feeds'      => false,
				'slug'       => 'function',
				'with_front' => false,
			),
			'supports'    => $supports,
		);

		/**
		 * Filter post type registration arguments for WP-Parser post types.
		 *
		 * @see register_post_type()
		 *
		 * @param array  $post_type_args Associative array of post registration arguments.
		 * @param string $post_type      Post type. Possible values are 'wp-parser-function',
		 *                               'wp-parser-class', 'wp-parser-method', and 'wp-parser-hook'.
		 */
		register_post_type( 'wp-parser-function', apply_filters( 'wp_parser_post_type_args', $function_args, 'wp-parser-function' ) );

		// Method post type.
		$method_args = array(
			'has_archive' => 'methods',
			'label'       => __( 'Methods', 'wp-parser' ),
			'labels'      => array(
				'name'               => __( 'Methods', 'wp-parser' ),
				'singular_name'      => __( 'Method', 'wp-parser' ),
				'all_items'          => __( 'Methods', 'wp-parser' ),
				'new_item'           => __( 'New Method', 'wp-parser' ),
				'add_new'            => __( 'Add New', 'wp-parser' ),
				'add_new_item'       => __( 'Add New Method', 'wp-parser' ),
				'edit_item'          => __( 'Edit Method', 'wp-parser' ),
				'view_item'          => __( 'View Method', 'wp-parser' ),
				'search_items'       => __( 'Search Methods', 'wp-parser' ),
				'not_found'          => __( 'No Methods found', 'wp-parser' ),
				'not_found_in_trash' => __( 'No Methods found in trash', 'wp-parser' ),
				'parent_item_colon'  => __( 'Parent Method', 'wp-parser' ),
				'menu_name'          => __( 'Methods', 'wp-parser' ),
			),
			'public'      => true,
			'rewrite'     => array(
				'feeds'      => false,
				'slug'       => 'method',
				'with_front' => false,
			),
			'supports'    => $supports,
		);
		/** This filter is documented in lib/class-plugin.php */
		register_post_type( 'wp-parser-method', apply_filters( 'wp_parser_post_type_args', $method_args, 'wp-parser-method' ) );

		add_rewrite_rule( 'method/([^/]+)/([^/]+)/?$', 'index.php?post_type=wp-parser-method&name=$matches[1]-$matches[2]', 'top' );

		// Class post type.
		$class_args = array(
			'has_archive' => 'classes',
			'label'       => __( 'Classes', 'wp-parser' ),
			'labels'      => array(
				'name'               => __( 'Classes', 'wp-parser' ),
				'singular_name'      => __( 'Class', 'wp-parser' ),
				'all_items'          => __( 'Classes', 'wp-parser' ),
				'new_item'           => __( 'New Class', 'wp-parser' ),
				'add_new'            => __( 'Add New', 'wp-parser' ),
				'add_new_item'       => __( 'Add New Class', 'wp-parser' ),
				'edit_item'          => __( 'Edit Class', 'wp-parser' ),
				'view_item'          => __( 'View Class', 'wp-parser' ),
				'search_items'       => __( 'Search Classes', 'wp-parser' ),
				'not_found'          => __( 'No Classes found', 'wp-parser' ),
				'not_found_in_trash' => __( 'No Classes found in trash', 'wp-parser' ),
				'parent_item_colon'  => __( 'Parent Class', 'wp-parser' ),
				'menu_name'          => __( 'Classes', 'wp-parser' ),
			),
			'public'      => true,
			'rewrite'     => array(
				'feeds'      => false,
				'slug'       => 'class',
				'with_front' => false,
			),
			'supports'    => $supports,
		);
		/** This filter is documented in lib/class-plugin.php */
		register_post_type( 'wp-parser-class', apply_filters( 'wp_parser_post_type_args', $class_args, 'wp-parser-class' ) );

		// Hook post type.
		$hook_args = array(
			'has_archive' => 'hooks',
			'label'       => __( 'Hooks', 'wp-parser' ),
			'labels'      => array(
				'name'               => __( 'Hooks', 'wp-parser' ),
				'singular_name'      => __( 'Hook', 'wp-parser' ),
				'all_items'          => __( 'Hooks', 'wp-parser' ),
				'new_item'           => __( 'New Hook', 'wp-parser' ),
				'add_new'            => __( 'Add New', 'wp-parser' ),
				'add_new_item'       => __( 'Add New Hook', 'wp-parser' ),
				'edit_item'          => __( 'Edit Hook', 'wp-parser' ),
				'view_item'          => __( 'View Hook', 'wp-parser' ),
				'search_items'       => __( 'Search Hooks', 'wp-parser' ),
				'not_found'          => __( 'No Hooks found', 'wp-parser' ),
				'not_found_in_trash' => __( 'No Hooks found in trash', 'wp-parser' ),
				'parent_item_colon'  => __( 'Parent Hook', 'wp-parser' ),
				'menu_name'          => __( 'Hooks', 'wp-parser' ),
			),
			'public'      => true,
			'rewrite'     => array(
				'feeds'      => false,
				'slug'       => 'hook',
				'with_front' => false,
			),
			'supports'    => $supports,
		);
		/** This filter is documented in lib/class-plugin.php */
		register_post_type( 'wp-parser-hook', apply_filters( 'wp_parser_post_type_args', $hook_args, 'wp-parser-hook' ) );
	}

	/**
	 * Register the file and "@since" taxonomies.
	 */
	public function register_taxonomies() {

		$object_types = array( 'wp-parser-class', 'wp-parser-method', 'wp-parser-function', 'wp-parser-hook' );

		// Source File taxonomy.
		$source_file_tax_args = array(
			'label'                 => __( 'Files', 'wp-parser' ),
			'labels'                => array(
				'name'                       => __( 'Files', 'wp-parser' ),
				'singular_name'              => _x( 'File', 'taxonomy general name', 'wp-parser' ),
				'search_items'               => __( 'Search Files', 'wp-parser' ),
				'popular_items'              => null,
				'all_items'                  => __( 'All Files', 'wp-parser' ),
				'parent_item'                => __( 'Parent File', 'wp-parser' ),
				'parent_item_colon'          => __( 'Parent File:', 'wp-parser' ),
				'edit_item'                  => __( 'Edit File', 'wp-parser' ),
				'update_item'                => __( 'Update File', 'wp-parser' ),
				'add_new_item'               => __( 'New File', 'wp-parser' ),
				'new_item_name'              => __( 'New File', 'wp-parser' ),
				'separate_items_with_commas' => __( 'Files separated by comma', 'wp-parser' ),
				'add_or_remove_items'        => __( 'Add or remove Files', 'wp-parser' ),
				'choose_from_most_used'      => __( 'Choose from the most used Files', 'wp-parser' ),
				'menu_name'                  => __( 'Files', 'wp-parser' ),
			),
			'public'                => true,
			'rewrite'               => array( 'slug' => 'files' ),
			'sort'                  => false,
			'update_count_callback' => '_update_post_term_count',
		);

		register_taxonomy(
			'wp-parser-source-file',
			$object_types,
			/**
			 * Filter registration arguments for WP-Parser taxonomies.
			 *
			 * @see register_taxonomy()
			 *
			 * @param array  $taxonomy_args Associative array of taxonomy registration arguments.
			 * @param string $taxonomy      Taxonomy. Possible values include 'wp-parser-source-file',
			 *                              'wp-parser-package', and'wp-parser-since'.
			 */
			apply_filters( 'wp_parser_taxonomy_args', $source_file_tax_args, 'wp-parser-source-file' )
		);

		// Package tag taxonomy.
		$package_tax_args = array(
			'hierarchical'          => true,
			'label'                 => '@package',
			'public'                => true,
			'rewrite'               => array( 'slug' => 'package' ),
			'sort'                  => false,
			'update_count_callback' => '_update_post_term_count',
		);

		register_taxonomy(
			'wp-parser-package',
			$object_types,
			/** This filter is documented in lib/class-plugin.php */
			apply_filters( 'wp_parser_taxonomy_args', $package_tax_args, 'wp-parser-package' );
		);

		// Since tag taxonomy.
		$since_tax_args = array(
			'hierarchical'          => true,
			'label'                 => __( '@since', 'wp-parser' ),
			'public'                => true,
			'rewrite'               => array( 'slug' => 'since' ),
			'sort'                  => false,
			'update_count_callback' => '_update_post_term_count',
		);

		register_taxonomy(
			'wp-parser-since',
			$object_types,
			/** This filter is documented in lib/class-plugin.php */
			apply_filters( 'wp_parser_taxonomy_args', $since_tax_args, 'wp-parser-since' );
		);
	}

	public function method_permalink( $link, $post ) {

		if ( $post->post_type !== 'wp-parser-method' || $post->post_parent == 0 ) {
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
		return str_replace( '|', '<span class="wp-parser-item-type-or">' . _x( ' or ', 'separator', 'wp-parser' ) . '</span>', $type );
	}
}
