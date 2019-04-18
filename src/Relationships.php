<?php

namespace WP_Parser;

use P2P_Query_Post;
use P2P_Storage;
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
		add_action( 'plugins_loaded', 			 [ $this, 'require_posts_to_posts' ] );
		add_action( 'wp_loaded', 				 [ $this, 'register_post_relationships' ] );
		add_action( 'wp_parser_import_item', 	 [ $this, 'import_item' ], 10, 3 );
		add_action( 'wp_parser_starting_import', [ $this, 'wp_parser_starting_import' ] );
		add_action( 'wp_parser_ending_import', 	 [ $this, 'wp_parser_ending_import' ] );
	}

	/**
	 * Load the posts2posts from the composer package if it is not loaded already.
	 */
	public function require_posts_to_posts() {
		// Initializes the database tables
		P2P_Storage::init();

		// Initializes the query mechanism
		P2P_Query_Post::init();
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
		$this->register_connection( 'functions_to_functions', 'wp-parser-function', 'wp-parser-function', [ 'from' => 'Uses Functions', 'to' => 'Used by Functions' ], true );
		$this->register_connection( 'functions_to_methods', 'wp-parser-function', 'wp-parser-method', [ 'from' => 'Uses Methods', 'to' => 'Used by Functions' ] );
		$this->register_connection( 'functions_to_hooks', 'wp-parser-function', 'wp-parser-hook', [ 'from' => 'Uses Hooks', 'to' => 'Used by Functions' ] );

		$this->register_connection( 'methods_to_functions', 'wp-parser-method', 'wp-parser-function', [ 'from' => 'Uses Hooks', 'to' => 'Used by Functions' ] );
		$this->register_connection( 'methods_to_methods', 'wp-parser-method', 'wp-parser-method', [ 'from' => 'Uses Methods', 'to' => 'Used by Methods' ] );
		$this->register_connection( 'methods_to_hooks', 'wp-parser-method', 'wp-parser-hook', [ 'from' => 'Used by Methods', 'to' => 'Uses Hooks' ] );
	}

	/**
	 * Registers a new connection.
	 *
	 * @param string $name 			   The name of the connection.
	 * @param string $from 			   The post type to connect from.
	 * @param string $to			   The post type to connect to.
	 * @param array  $titles		   The titles to assign
	 * @param bool   $self_connections Whether to allow a post/user to connect to itself.
	 *
	 * @return void
	 */
	protected function register_connection( string $name, string $from, string $to, array $titles, $self_connections = false ) {
		$connection = [
			'name' 	=> $name,
			'from' 	=> $from,
			'to' 	=> $to,
			'title' => $titles,
		];

		if ( $self_connections === true ) {
			$connection['self_connections'] = 'true';
		}

		p2p_register_connection_type( $connection );
	}

	/**
	 * Bring Importer post types into this class.
	 * Runs at import start.
	 */
	public function wp_parser_starting_import() {
		$importer = new Importer;

		if ( ! $this->p2p_tables_exist() ) {
			P2P_Storage::init();
			P2P_Storage::install();
		}

		$this->post_types = array(
			'hook' 		=> $importer->post_type_hook,
			'method' 	=> $importer->post_type_method,
			'function' 	=> $importer->post_type_function,
		);
	}

	/**
	 * Checks to see if the posts to posts tables exist and returns if they do
	 *
	 * @return bool Whether or not the posts 2 posts tables exist.
	 */
	public function p2p_tables_exist() {
		global $wpdb;

		$tables = $wpdb->get_col( 'SHOW TABLES' );

		// There is no way to get the name out of P2P so we hard code it here.
		return in_array( $wpdb->prefix . 'p2p', $tables, true );
	}

	/**
	 * As each item imports, build an array mapping it's post_type->slug to it's post ID.
	 * These will be used to associate post IDs to each other without doing an additional
	 * database query to map each post's slug to its ID.
	 *
	 * @param int   $post_id   Post ID of item just imported.
	 * @param array $data      Parser data
	 * @param array $post_data Post data
	 *
	 * @return void
	 */
	public function import_item( $post_id, $data, $post_data ) {
		$from_type = $post_data['post_type'];

		$this->slugs_to_ids[ $from_type ][ $post_data['post_name'] ] = $post_id;

		$sub_types = [
			'function' => 'functions',
			'method' => 'methods',
			'hook' => 'hooks'
		];

		if ( $from_type === $this->post_types['function'] || $from_type === $this->post_types['method'] ) {
			foreach ( $sub_types as $to => $from ) {
				if ( ! array_key_exists( 'uses', $data ) || ! array_key_exists( $from, $data['uses'] ) ) {
					continue;
				}

				$to_type = $this->post_types[ $to ];

				foreach ( (array) $data['uses'][ $from ] as $to_item ) {
					if ( ! is_string( $to_item['name'] ) ) { // might contain variable node for dynamic calls
						continue;
					}

					$to_item_slug = $to_item['name'];

					// If linking to a method, we need to alter the slug in case it's static or references a class.
					if ( $to === 'method' && ( $to_item['static'] || ! empty( $to_item['class'] ) ) ) {
						$to_item_slug = $to_item['class'] . '-' . $to_item['name'];
					}

					$namespace = ( $to === 'hook' ) ? null : $data['namespace'];

					$this->add_relationship( $post_id, $from_type, $to_type, $to_item_slug, $namespace );
				}
			}
		}
	}

	/**
	 * Adds a relation to the stack.
	 *
	 * @param      $post_id	  The post ID.
	 * @param      $from	  The item to relate from.
	 * @param      $to		  The item to relate to.
	 * @param      $name	  The name to apply to the relationship.
	 * @param null $namespace The namespace to add.
	 */
	protected function add_relationship( $post_id, $from, $to, $name, $namespace = null ) {
		$this->relationships[ $from ][ $post_id ][ $to ][] = $this->names_to_slugs( $name, $namespace );
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
	 * Map a name to slug, taking into account namespace context.
	 *
	 * When a function is called within a namespace, the function is first looked
	 * for in the current namespace. If it exists, the namespaced version is used.
	 * If the function does not exist in the current namespace, PHP tries to find
	 * the function in the global scope.
	 *
	 * Unless the call has been prefixed with '\' indicating it is fully qualified
	 * we need to check first in the current namespace and then in the global
	 * scope.
	 *
	 * This also catches the case where relative namespaces are used. You can
	 * create a file in namespace `\Foo` and then call a funtion called `baz` in
	 * namespace `\Foo\Bar\` by just calling `Bar\baz()`. PHP will first look
	 * for `\Foo\Bar\baz()` and if it can't find it fall back to `\Bar\baz()`.
	 *
	 * @see    WP_Parser\Importer::import_item()
	 * @param  string $name      The name of the item a slug is needed for.
	 * @param  string $namespace The namespace the item is in when for context.
	 * @return array             An array of slugs, starting with the context of the
	 *                           namespace, and falling back to the global namespace.
	 */
	public function names_to_slugs( $name, $namespace = null ) {
		$fully_qualified = ( 0 === strpos( '\\', $name ) );
		$name = ltrim( $name, '\\' );
		$names = array();

		if ( $namespace && ! $fully_qualified  ) {
			$names[] = $this->name_to_slug( $namespace . '\\' . $name );
		}
		$names[] = $this->name_to_slug( $name );

		return $names;
	}

	/**
	 * Simple conversion of a method, function, or hook name to a post slug.
	 *
	 * Replaces '::' and '\' to dashes and then runs the name through `sanitize_title()`.
	 *
	 * @param  string $name Method, function, or hook name
	 * @return string       The post slug for the passed name.
	 */
	public function name_to_slug( $name ) {
		return sanitize_title( str_replace( '\\', '-', str_replace( '::', '-', $name ) ) );
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

		foreach ( $slugs as $index => $scoped_slugs ) {
			// Find the first matching scope the ID exists for.
			foreach ( $scoped_slugs as $slug ) {
				if ( array_key_exists( $slug, $slugs_to_ids ) ) {
					$slugs_with_ids[ $slug ] = $slugs_to_ids[ $slug ];
					// if we found it in this scope, stop searching the chain.
					continue;
				}
			}
		}

		return $slugs_with_ids;
	}
}
