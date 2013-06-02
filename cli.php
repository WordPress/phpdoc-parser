<?php
use phpDocumentor\Reflection\FileReflector;

/**
 * Converts PHPDoc markup into a template ready for import to a WordPress blog.
 */
class WP_PHPDoc_Command extends WP_CLI_Command {

	/**
	 * Generate a JSON file containing the PHPDoc markup, and save to filesystem.
	 *
	 * @synopsis <directory> [<output_file>]
	 */
	public function generate( $args ) {
		list( $directory, $output_file ) = $args;

		if ( empty( $output_file ) )
			$output_file = 'phpdoc.xml';

		$directory = realpath( $directory );
		$this->_load_libs();
		WP_CLI::line();

		// Get data from the PHPDoc
		$json = $this->_get_phpdoc_data( $directory );

		// Write to $output_file
		$error = ! file_put_contents( $output_file, $json );
		if ( $error ) {
			WP_CLI::error( sprintf( 'Problem writing %1$s bytes of data to %2$s', strlen( $json ), $output_file ) );
			exit;
		}

		WP_CLI::success( sprintf( 'Data exported to %1$s', $output_file ) );
		WP_CLI::line();
	}

	/**
	 * Read a JSON file containing the PHPDoc markup, convert it into WordPress posts, and insert into DB.
	 *
	 * @synopsis <file>
	 */
	public function import( $args, $assoc_args ) {
		list( $file ) = $args;
		$this->_load_libs();
		WP_CLI::line();

		// Get the data from the <file>, and check it's valid.
		$phpdoc = false;
		if ( is_readable( $file ) )
			$phpdoc = file_get_contents( $file );

		if ( ! $phpdoc ) {
			WP_CLI::error( sprintf( "Can't read %1\$s. Does the file exist?", $file ) );
			exit;
		}

		$phpdoc = json_decode( $phpdoc );
		if ( is_null( $phpdoc ) ) {
			WP_CLI::error( sprintf( "JSON in %1\$s can't be decoded :(", $file ) );
			exit;
		}

		// Import data
		$this->_do_import( $phpdoc );
	}

	/**
	 * Generate JSON containing the PHPDoc markup, convert it into WordPress posts, and insert into DB.
	 *
	 * @subcommand generate-and-import
	 * @synopsis <directory>
	 */
	public function generate_and_import( $args ) {
		list( $directory ) = $args;
		$directory = realpath( $directory );
		$this->_load_libs();
		WP_CLI::line();

		// Import data
		$this->_do_import( $this->_get_phpdoc_data( $directory, 'array' ) );
	}


	/**
	 * Loads required libraries from WP-Parser project
	 *
	 * @see https://github.com/rmccue/WP-Parser/
	 */
	protected function _load_libs() {
		$path = dirname( __FILE__ ). '/WP-Parser/';

		require_once "$path/vendor/autoload.php";
		require_once "$path/lib/WP/runner.php";
	}

	/**
	 * Generate the data from the PHPDoc markup.
	 *
	 * @param string $path Directory to scan for PHPDoc
	 * @param string $format Optional. What format the data is returned in: [json*|array].
	 * @return string
	 */
	protected function _get_phpdoc_data( $path, $format = 'json' ) {
		WP_CLI::line( sprintf( 'Extracting PHPDoc from %1$s/. This may take a few minutes...', $path ) );

		// Find the files to get the PHPDoc data from. $path can either be a folder or an absolute ref to a file.
		if ( is_file( $path ) ) {
			$files = array( $path );
			$path  = dirname( $path );

		} else {
			ob_start();
			$files = get_wp_files( $path );
			$error = ob_get_clean();

			if ( $error ) {
				WP_CLI::error( sprintf( 'Problem with %1$s: %2$s', $path, $error ) );
				exit;
			}
		}

		// Extract PHPDoc
		$output = parse_files( $files, $path );

		if ( $format == 'json' )
			$output = json_encode( $output, JSON_PRETTY_PRINT );

		return $output;
	}

	/**
	 * Import the PHPDoc $data into WordPress posts and taxonomies
	 *
	 * @param array $data
	 */
	protected function _do_import( array $data ) {

		// Make sure a current user is set
		if ( ! wp_get_current_user()->exists() ) {
			WP_CLI::error( 'Please specify a valid user: --user=<id|login>' );
			exit;
		}

		WP_CLI::line( 'Starting import. This will take some time…' );

		$file_number  = 1;
		$num_of_files = count( $data );

		// Defer term counting for performance
		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );

		// Run the importer
		$importer = new WP_PHPDoc_Importer;

		// Sanity check -- do the required post types exist?
		if ( ! post_type_exists( $importer->post_type_class ) || ! post_type_exists( $importer->post_type_function ) ) {
			WP_CLI::error( sprintf( 'Missing post type; check that "%1$s" and "%2$s" are registered.', $importer->post_type_class, $importer->post_type_function ) );
			exit;
		}

		// Sanity check -- do the required taxonomies exist?
		if ( ! taxonomy_exists( $importer->taxonomy_file ) ) {
			WP_CLI::error( sprintf( 'Missing taxonomy; check that "%1$s" is registered.', $importer->taxonomy_file ) );
			exit;
		}

		foreach ( $data as $file ) {
			WP_CLI::line( sprintf( 'Processing file %1$s of %2$s.', number_format_i18n( $file_number ) , number_format_i18n( $num_of_files ) ) );
			$file_number++;

			$importer->import_file( $file );
		}

		// Start counting again
		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );

		if ( empty( $importer->errors ) ) {
			WP_CLI::success( 'Import complete!' );

		} else {
			WP_CLI::line( 'Import complete, but some errors were found:' );
			foreach ( $importer->errors as $error )
				WP_CLI::warning( $error );
		}

		WP_CLI::line();
	}
}
WP_CLI::add_command( 'phpdoc', 'WP_PHPDoc_Command' );

/**
 * Handles creating and updating posts from (functions|classes|files) generated by phpDoc.
 *
 * Based on the Importer class from https://github.com/rmccue/WP-Parser/
 */
class WP_PHPDoc_Importer {
	public $taxonomy_file;
	public $taxonomy_package;  // todo
	public $post_type_function;
	public $post_type_class;
	public $post_type_hook;  // todo

	/**
	 * Stores a reference to the current file's term in the file taxonomy
	 *
	 * @var string
	 */
	public $file_term_id;

	/**
	 * @var array Human-readable errors
	 */
	public $errors = array();


	/**
	 * Constructor. Sets up post type/taxonomy names.
	 *
	 * @param string $class Optional. Post type name for classes.
	 * @param string $file Optional. Taxonony name for files.
	 * @param string $function Optional. Post type name for functions.
	 */
	public function __construct( $class = 'wpapi-class', $file = 'wpapi-source-file', $function = 'wpapi-function' ) {
		$this->post_type_class    = $class;
		$this->post_type_function = $function;
		$this->taxonomy_file      = $file;
	}

	/**
	 * For a specific file, go through and import the file, functions, and classes.
	 *
	 * @param array $file
	 */
	public function import_file( array $file ) {

		// Maybe add an item for this file to the file taxonomy
		$slug = sanitize_title( str_replace( '/', '_', $file['path'] ) );
		$term = get_term_by( 'slug', $slug, $this->taxonomy_file, ARRAY_A );
		if ( ! $term ) {

			$term = wp_insert_term( $file['path'], $this->taxonomy_file, array( 'slug' => $slug ) );
			if ( is_wp_error( $term ) ) {
				$this->errors[] = sprintf( 'Problem creating file tax item "%1$s" for %2$s: %3$s', $slug, $file['path'], $term->get_error_message() );
				return;
			}

			// Grab the full term object
			$term = get_term_by( 'slug', $slug, $this->taxonomy_file, ARRAY_A );
		}

		$this->file_term_id = $term['name'];

		// Functions
		if ( ! empty( $file['functions'] ) ) {
			$i = 0;

			foreach ( $file['functions'] as $function ) {
				$this->import_function( $function );
				$i++;

				// Wait 3 seconds after every 10 items
				if ( $i % 10 == 0 )
					sleep( 3 );
			}
		}

		// Classes
		if ( ! empty( $file['classes'] ) ) {
			$i = 0;

			// @todo Temporarily disabled class/method generation until the templates are sorted out
			foreach ( $file['classes'] as $class ) {
				//$this->import_class( $class );
				$i++;

				// Wait 3 seconds after every 10 items
				if ( $i % 10 == 0 )
					sleep( 3 );
			}
		}
	}

	/**
	 * Remove inline newlines in the $input string.
	 *
	 * This tidies up a block of text from phpDoc where the author split the block over multiple lines.
	 * We remove the inline newlines and replace with a space to avoid getting the end of one line being
	 * joined to the beginning of the next line, without any space inbetween.
	 *
	 * This regex was taken from wpautop().
	 *
	 * @param string $input
	 * @return string
	 */
	public static function _fix_linebreaks( $input ) {
		return preg_replace( '|(?<!<br />)\s*\n|', ' ', $input );
	}

	/**
	 * Get the template for function pages' post_content 
	 *
	 * @param array $function_data Function data from the PHPDoc used to populate this template
	 * @return string
	 */
	public static function _get_function_template( array $function_data ) {

		// Long description
		$long_description = self::_fix_linebreaks( $function_data['doc']['long_description'] );

		// Removing wrapping paragraph tags; see https://github.com/rmccue/WP-Parser/issues/6
		$long_description = substr( $long_description, strlen( '<p>' ) );
		$long_description = substr( $long_description, 0, strlen( $long_description ) - strlen( '</p>' ) );

		return self::_fix_linebreaks( $long_description );
	}

	/**
	 * Create a post for a function
	 *
	 * @param array $data Function
	 * @param int $class_post_id Optional; post ID of the class this method belongs to. Defaults to zero (not a method).
	 */
	public function import_function( array $data, $class_post_id = 0 ) {
		global $wpdb;

		$is_new_post = true;
		$slug        = sanitize_title( $data['name'] );
		$post_data   = array(
			'post_content' => self::_get_function_template( $data ),
			'post_excerpt' => self::_fix_linebreaks( $data['doc']['description'] ),
			'post_name'    => $slug,
			'post_parent'  => (int) $class_post_id,
			'post_status'  => 'publish',
			'post_title'   => $data['name'],
			'post_type'    => $this->post_type_function,
		);

		// Look for an existing post for this function
		$existing_post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND post_parent = %d LIMIT 1", $slug, $this->post_type_function, (int) $class_post_id ) );

		// Insert/update the function post
		if ( ! empty( $existing_post_id ) ) {
			$is_new_post     = false;
			$post_data['ID'] = (int) $existing_post_id;
			$ID              = wp_update_post( $post_data, true );

		} else {
			$ID = wp_insert_post( $post_data );
		}

		if ( ! $ID || is_wp_error( $ID ) ) {
			$this->errors[] = sprintf( 'Problem inserting/updating post for function "%1$s": %2$s', $data['name'], $ID->get_error_message() );
			return;
		}

		// Set taxonomy and post meta to use in the theme template
		wp_set_object_terms( $ID, $this->file_term_id, $this->taxonomy_file );

		update_post_meta( $ID, '_wpapi_args',     $data['arguments'] );
		update_post_meta( $ID, '_wpapi_line_num', $data['line'] );
		update_post_meta( $ID, '_wpapi_tags',     $data['doc']['tags']);

		if ( $class_post_id ) {
			update_post_meta( $ID, '_wpapi_final',      (bool) $data['final'] );
			update_post_meta( $ID, '_wpapi_abstract',   (bool) $data['abstract'] );
			update_post_meta( $ID, '_wpapi_static',     (bool) $data['static'] );
			update_post_meta( $ID, '_wpapi_visibility',        $data['visibility'] );
		}

		// Everything worked! Woo hoo!
		if ( $is_new_post ) {
			if ( $class_post_id )
				WP_CLI::line( sprintf( "\tImported method \"%1\$s\"", $data['name'] ) );
			else
				WP_CLI::line( sprintf( "\tImported function \"%1\$s\"", $data['name'] ) );

		} else {
			if ( $class_post_id )
				WP_CLI::line( sprintf( "\tUpdated method \"%1\$s\"", $data['name'] ) );
			else
				WP_CLI::line( sprintf( "\tUpdated function \"%1\$s\"", $data['name'] ) );
		}
	}

	/**
	 * Create a post for a class
	 *
	 * @param array $data Class
	 */
	protected function import_class( array $data ) {
		global $wpdb;

		$is_new_post = true;
		$slug        = sanitize_title( $data['name'] );
		$post_data   = array(
			'name'         => $slug,
			'post_content' => $data['doc']['long_description'],
			'post_excerpt' => $data['doc']['description'],
			'post_status'  => 'publish',
			'post_title'   => $data['name'],
			'post_type'    => $this->post_type_class,
		);

		// Look for an existing post for this class
		$existing_post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = %s LIMIT 1", $slug, $this->post_type_class ) );

		// Insert/update the function post
		if ( ! empty( $existing_post_id ) ) {
			$is_new_post     = false;
			$post_data['ID'] = (int) $existing_post_id;
			$ID              = wp_update_post( $post_data, true );

		} else {
			$ID = wp_insert_post( $post_data, true );
		}

		if ( ! $ID || is_wp_error( $ID ) ) {
			$this->errors[] = sprintf( 'Problem inserting/updating post for class "%1$s": %2$s', $data['name'], $ID->get_error_message() );
			return;
		}

		// Set taxonomy and post meta to use in the theme template
		wp_set_object_terms( $ID, $this->file_term_id, $this->taxonomy_file );

		update_post_meta( $ID, '_wpapi_line_num',   $data['line'] );
		update_post_meta( $ID, '_wpapi_properties', $data['properties'] );

		// Everything worked! Woo hoo!
		if ( $is_new_post )
			WP_CLI::line( sprintf( "\tImported class \"%1\$s\"", $data['name'] ) );
		else
			WP_CLI::line( sprintf( "\tUpdated class \"%1\$s\"", $data['name'] ) );

		// Now add this class's methods
		foreach ( $data['methods'] as $method )
			$this->import_function( $method, $ID );
	}
}
