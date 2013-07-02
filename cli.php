<?php

namespace WPFuncRef;

use phpDocumentor\Reflection\FileReflector;
use WP_CLI;
use WP_CLI_Command;

/**
 * Converts PHPDoc markup into a template ready for import to a WordPress blog.
 */
class Command extends WP_CLI_Command {

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
	 * @synopsis <file> [--quick] [--import-internal]
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
		$this->_do_import( $phpdoc, isset( $assoc_args['quick'] ), isset( $assoc_args['import-internal'] ) );
	}

	/**
	 * Generate JSON containing the PHPDoc markup, convert it into WordPress posts, and insert into DB.
	 *
	 * @subcommand generate-and-import
	 * @synopsis <directory> [--quick] [--import-internal]
	 */
	public function generate_and_import( $args, $assoc_args ) {
		list( $directory ) = $args;
		$directory = realpath( $directory );

		$this->_load_libs();
		WP_CLI::line();

		// Import data
		$this->_do_import( $this->_get_phpdoc_data( $directory, 'array' ), isset( $assoc_args['quick'] ), isset( $assoc_args['import-internal'] ) );
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
		$is_file = is_file( $path );
		WP_CLI::line( sprintf( 'Extracting PHPDoc from %1$s. This may take a few minutes...', $is_file ? $path : "$path/" ) );

		// Find the files to get the PHPDoc data from. $path can either be a folder or an absolute ref to a file.
		if ( $is_file ) {
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
	 * @param bool $skip_sleep Optional; defaults to false. If true, the sleep() calls are skipped.
	 * @param bool $import_internal_functions Optional; defaults to false. If true, functions marked @internal will be imported.
	 */
	protected function _do_import( array $data, $skip_sleep = false, $import_internal_functions = false ) {

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
		$importer = new Importer;

		// Sanity check -- do the required post types exist?
		if ( ! post_type_exists( $importer->post_type_class ) || ! post_type_exists( $importer->post_type_function ) ) {
			WP_CLI::error( sprintf( 'Missing post type; check that "%1$s" and "%2$s" are registered.', $importer->post_type_class, $importer->post_type_function ) );
			exit;
		}

		// Sanity check -- does the file taxonomy exist?
		if ( ! taxonomy_exists( $importer->taxonomy_file ) ) {
			WP_CLI::error( sprintf( 'Missing taxonomy; check that "%1$s" is registered.', $importer->taxonomy_file ) );
			exit;
		}

		// Sanity check -- does the @since taxonomy exist?
		if ( ! taxonomy_exists( $importer->taxonomy_since_version ) ) {
			WP_CLI::error( sprintf( 'Missing taxonomy; check that "%1$s" is registered.', $importer->taxonomy_since_version ) );
			exit;
		}

		foreach ( $data as $file ) {
			WP_CLI::line( sprintf( 'Processing file %1$s of %2$s.', number_format_i18n( $file_number ) , number_format_i18n( $num_of_files ) ) );
			$file_number++;

			$importer->import_file( $file, $skip_sleep, $import_internal_functions );
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

WP_CLI::add_command( 'funcref', __NAMESPACE__ . '\\Command' );
