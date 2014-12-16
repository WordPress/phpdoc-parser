<?php

namespace WP_Parser;

use WP_CLI;

/**
 * Registers and implements relationships with Posts 2 Posts.
 */
class Relationships {

	/**
	 * @var array Post types we're setting relationships between
	 */
	public $post_types;

	/**
	 * @var array Map of post slugs to post ids.
	 */
	public $slugs_to_ids = array();

	/**
	 * Map of how post IDs relate to one another.
	 *
	 * array(
	 *   $from_type => array(
	 *     $from_id => array(
	 *       $to_type => array(
	 *         $to_slug => $to_id
	 *       )
	 *     )
	 *   )
	 * )
	 *
	 * @var array
	 */
	public $relationships = array();

	/**
	 * Adds the actions.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'require_posts_to_posts' ) );
		add_action( 'wp_loaded', array( $this, 'register_post_relationships' ) );

		add_action( 'wp_parser_import_item', array( $this, 'import_item' ), 10, 3 );
		add_action( 'wp_parser_starting_import', array( $this, 'wp_parser_starting_import' ) );
		add_action( 'wp_parser_ending_import', array( $this, 'wp_parser_ending_import' ) );
	}

	/**
	 * Load the posts2posts from the composer package if it is not loaded already.
	 */
	public function require_posts_to_posts() {
		// Initializes the database tables
		\P2P_Storage::init();

		// Initializes the query mechanism
		\P2P_Query_Post::init();
	}

	/**
	 * Set up relationships using Posts to Posts plugin.
	 *
	 * Default settings for p2p_register_connection_type:
	 *   'cardinality' => 'many-to-many'
	 *   'reciprocal' => false
	 *
	 * @link  https://github.com/scribu/wp-posts-to-posts/wiki/p2p_register_connection_type
	 */
	public function register_post_relationships() {

		/*
		 * Functions to functions, methods and hooks
		 */
		p2p_register_connection_type( array(
			'name' => 'functions_to_functions',
			'from' => 'wp-parser-function',
			'to' => 'wp-parser-function',
			'self_connections' => 'true',
			'title' => array( 'from' => 'Uses Functions', 'to' => 'Used by Functions' ),
		) );

		p2p_register_connection_type( array(
			'name' => 'functions_to_methods',
			'from' => 'wp-parser-function',
			'to' => 'wp-parser-method',
			'title' => array( 'from' => 'Uses Methods', 'to' => 'Used by Functions' ),
		) );

		p2p_register_connection_type( array(
			'name' => 'functions_to_hooks',
			'from' => 'wp-parser-function',
			'to' => 'wp-parser-hook',
			'title' => array( 'from' => 'Uses Hooks', 'to' => 'Used by Functions' ),
		) );

		/*
		 * Methods to functions, methods and hooks
		 */
		p2p_register_connection_type( array(
			'name' => 'methods_to_functions',
			'from' => 'wp-parser-method',
			'to' => 'wp-parser-function',
			'title' => array( 'from' => 'Uses Functions', 'to' => 'Used by Methods' ),
		) );

		p2p_register_connection_type( array(
			'name' => 'methods_to_methods',
			'from' => 'wp-parser-method',
			'to' => 'wp-parser-method',
			'self_connections' => 'true',
			'title' => array( 'from' => 'Uses Methods', 'to' => 'Used by Methods' ),
		) );

		p2p_register_connection_type( array(
			'name' => 'methods_to_hooks',
			'from' => 'wp-parser-method',
			'to' => 'wp-parser-hook',
			'title' => array( 'from' => 'Used by Methods', 'to' => 'Uses Hooks' ),
		) );
	}

	/**
	 * Bring Importer post types into this class.
	 * Runs at import start.
	 */
	public function wp_parser_starting_import() {
		$importer = new Importer;

		$this->post_types = array(
			'hook' => $importer->post_type_hook,
			'method' => $importer->post_type_method,
			'function' => $importer->post_type_function,
		);
	}

	/**
	 * As each item imports, build an array mapping it's post_type->slug to it's post ID.
	 * These will be used to associate post IDs to each other without doing an additional
	 * database query to map each post's slug to its ID.
	 *
	 * @param int   $post_id   Post ID of item just imported.
	 * @param array $data      Parser data
	 * @param array $post_data Post data
	 */
	public function import_item( $post_id, $data, $post_data ) {

		$from_type = $post_data['post_type'];
		$slug = $post_data['post_name'];

		$this->slugs_to_ids[ $from_type ][ $slug ] = $post_id;

		// Build Relationships: Functions
		if ( $this->post_types['function'] == $from_type ) {

			// Functions to Functions
			$to_type = $this->post_types['function'];
			foreach ( (array) @$data['uses']['functions'] as $to_function ) {
				$to_function_slug = $this->name_to_slug( $to_function['name'] );

				$this->relationships[ $from_type ][ $post_id ][ $to_type ][] = $to_function_slug;
			}

			// Functions to Methods
			$to_type = $this->post_types['method'];
			foreach ( (array) @$data['uses']['methods'] as $to_method ) {

				if ( $to_method['static'] || ! empty( $to_method['class'] ) ) {
					$to_method_slug = $to_method['class'] . '-' . $to_method['name'];
				} else {
					$to_method_slug = $to_method['name'];
				}
				$to_method_slug = $this->name_to_slug( $to_method_slug );

				$this->relationships[ $from_type ][ $post_id ][ $to_type ][] = $to_method_slug;
			}

			// Functions to Hooks
			$to_type = $this->post_types['hook'];
			foreach ( (array) @$data['hooks'] as $to_hook ) {
				$to_hook_slug = $this->name_to_slug( $to_hook['name'] );

				$this->relationships[ $from_type ][ $post_id ][ $to_type ][] = $to_hook_slug;
			}
		}

		if ( $this->post_types['method'] === $from_type ) {

			// Methods to Functions
			$to_type = $this->post_types['function'];
			foreach ( (array) @$data['uses']['functions'] as $to_function ) {
				$to_function_slug = $this->name_to_slug( $to_function['name'] );

				$this->relationships[ $from_type ][ $post_id ][ $to_type ][] = $to_function_slug;
			}

			// Methods to Methods
			$to_type = $this->post_types['method'];
			foreach ( (array) @$data['uses']['methods'] as $to_method ) {

				if ( ! is_string( $to_method['name'] ) ) { // might contain variable node for dynamic method calls
					continue;
				}

				if ( $to_method['static'] || ! empty( $to_method['class'] ) ) {
					$to_method_slug = $to_method['class'] . '-' . $to_method['name'];
				} else {
					$to_method_slug = $to_method['name'];
				}
				$to_method_slug = $this->name_to_slug( $to_method_slug );

				$this->relationships[ $from_type ][ $post_id ][ $to_type ][] = $to_method_slug;
			}

			// Methods to Hooks
			$to_type = $this->post_types['hook'];
			foreach ( (array) @$data['hooks'] as $to_hook ) {
				$to_hook_slug = $this->name_to_slug( $to_hook['name'] );

				$this->relationships[ $from_type ][ $post_id ][ $to_type ][] = $to_hook_slug;
			}
		}

	}

	/**
	 * After import has run, go back and connect all the posts.
	 */
	public function wp_parser_ending_import() {

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::log( 'Removing current relationships...' );
		}

		p2p_delete_connections( 'functions_to_functions' );
		p2p_delete_connections( 'functions_to_methods' );
		p2p_delete_connections( 'functions_to_hooks' );
		p2p_delete_connections( 'methods_to_functions' );
		p2p_delete_connections( 'methods_to_methods' );
		p2p_delete_connections( 'methods_to_hooks' );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::log( 'Setting up relationships...' );
		}

		// Iterate over post types being related FROM: functions, methods, and hooks
		foreach ( $this->post_types as $from_type ) {
			// Iterate over relationships for each post type
			foreach ( (array) @$this->relationships[ $from_type ] as $from_id => $to_types ) {

				// Iterate over slugs for each post type being related TO
				foreach ( $to_types as $to_type => $to_slugs ) {
					// Convert slugs to IDs.

					if ( empty( $this->slugs_to_ids[ $to_type ] ) ) { // TODO why might this be empty? test class-IXR.php
						continue;
					}

					$this->relationships[ $from_type ][ $from_id ][ $to_type ] = $this->get_ids_for_slugs( $to_slugs, $this->slugs_to_ids[ $to_type ] );
				}
			}
		}

		// Repeat loop over post_types and relationships now that all slugs have been mapped to IDs
		foreach ( $this->post_types as $from_type ) {
			foreach ( (array) @$this->relationships[ $from_type ] as $from_id => $to_types ) {

				// Connect Functions
				if ( $from_type == $this->post_types['function'] ) {

					foreach ( $to_types as $to_type => $to_slugs ) {
						// ...to Functions
						if ( $this->post_types['function'] == $to_type ) {
							foreach ( $to_slugs as $to_slug => $to_id ) {
								$to_id = intval( $to_id, 10 );
								if ( 0 != $to_id ) {
									p2p_type( 'functions_to_functions' )->connect( $from_id, $to_id, array( 'date' => current_time( 'mysql' ) ) );
								}
							}
						}
						// ...to Methods
						if ( $this->post_types['method'] == $to_type ) {
							foreach ( $to_slugs as $to_slug => $to_id ) {
								$to_id = intval( $to_id, 10 );
								if ( 0 != $to_id ) {
									p2p_type( 'functions_to_methods' )->connect( $from_id, $to_id, array( 'date' => current_time( 'mysql' ) ) );
								}
							}
						}
						// ...to Hooks
						if ( $this->post_types['hook'] == $to_type ) {
							foreach ( $to_slugs as $to_slug => $to_id ) {
								$to_id = intval( $to_id, 10 );
								if ( 0 != $to_id ) {
									p2p_type( 'functions_to_hooks' )->connect( $from_id, $to_id, array( 'date' => current_time( 'mysql' ) ) );
								}
							}
						}
					}
				}

				// Connect Methods
				if ( $from_type === $this->post_types['method'] ) {

					foreach ( $to_types as $to_type => $to_slugs ) {

						// ...to Functions
						if ( $this->post_types['function'] === $to_type ) {
							foreach ( $to_slugs as $to_slug => $to_id ) {
								$to_id = intval( $to_id, 10 );
								if ( 0 != $to_id ) {
									p2p_type( 'methods_to_functions' )->connect( $from_id, $to_id, array( 'data' => current_time( 'mysql' ) ) );
								}
							}
						}

						// ...to Methods
						if ( $this->post_types['method'] === $to_type ) {
							foreach ( $to_slugs as $to_slug => $to_id ) {
								$to_id = intval( $to_id, 10 );
								if ( 0 != $to_id ) {
									p2p_type( 'methods_to_methods' )->connect( $from_id, $to_id, array( 'data' => current_time( 'mysql' ) ) );
								}
							}
						}

						// ...to Hooks
						if ( $this->post_types['hook'] === $to_type ) {
							foreach ( $to_slugs as $to_slug => $to_id ) {
								$to_id = intval( $to_id, 10 );
								if ( 0 != $to_id ) {
									p2p_type( 'methods_to_hooks' )->connect( $from_id, $to_id, array( 'data' => current_time( 'mysql' ) ) );
								}
							}
						}
					}
				}
			}
		}

	}

	/**
	 * Convert a method, function, or hook name to a post slug.
	 *
	 * @see    WP_Parser\Importer::import_item()
	 * @param  string $name Method, function, or hook name
	 * @return string       Post slug
	 */
	public function name_to_slug( $name ) {
		return sanitize_title( str_replace( '::', '-', $name ) );
	}

	/**
	 * Convert a post slug to an array( 'slug' => id )
	 * Ignores slugs that are not found in $slugs_to_ids
	 *
	 * @param  array $slugs         Array of post slugs.
	 * @param  array $slugs_to_ids  Map of slugs to IDs.
	 * @return array
	 */
	public function get_ids_for_slugs( array $slugs, array $slugs_to_ids ) {
		$slugs_with_ids = array();

		foreach ( $slugs as $index => $slug ) {
			if ( array_key_exists( $slug, $slugs_to_ids ) ) {
				$slugs_with_ids[ $slug ] = $slugs_to_ids[ $slug ];
			}
		}

		return $slugs_with_ids;
	}
}
