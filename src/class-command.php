<?php

namespace WP_Parser;

use WP_CLI;
use WP_CLI_Command;

// Temporary toggle until we figure out what's going wrong with the plugins taxonomy.
const USE_PLUGIN_PREFIX = false;

/**
 * Converts PHPDoc markup into a template ready for import to a WordPress blog.
 */
class Command extends WP_CLI_Command {

	/**
	 * Generate a JSON file containing the PHPDoc markup, and save to filesystem.
	 *
	 * @synopsis <directory> [<output_file>] [--ignore_files]
	 *
	 * @param array $args       The arguments to pass to the command.
	 * @param array $assoc_args The associated arguments to pass to the command.
	 *
	 * @throws \phpDocumentor\Reflection\Exception\UnparsableFile
	 * @throws \phpDocumentor\Reflection\Exception\UnreadableFile
	 */
	public function export( $args, $assoc_args ) {
		$directory    = realpath( $args[0] );
		$output_file  = empty( $args[1] ) ? 'phpdoc.json' : $args[1];
		$ignore_files = empty( $assoc_args['ignore_files'] ) ? array() : explode( ',', $assoc_args['ignore_files'] );

		$json        = $this->_get_phpdoc_data( $directory, 'json', $ignore_files );
		$result      = file_put_contents( $output_file, $json );
		WP_CLI::line();

		if ( false === $result ) {
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
	 *
	 * @param array $args		The arguments to pass to the command.
	 * @param array $assoc_args The associated arguments to pass to the command.
	 */
	public function import( $args, $assoc_args ) {
		list( $file ) = $args;
		WP_CLI::line();

		// Get the data from the <file>, and check it's valid.
		$phpdoc = false;

		if ( is_readable( $file ) ) {
			$phpdoc = file_get_contents( $file );
		}

		if ( ! $phpdoc ) {
			WP_CLI::error( sprintf( "Can't read %1\$s. Does the file exist?", $file ) );
			exit;
		}

		$phpdoc = json_decode( $phpdoc, true );
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
	 * @subcommand create
	 * @synopsis   <directory> [--quick] [--import-internal] [--user] [--ignore_files]
	 *
	 * @param array $args       The arguments to pass to the command.
	 * @param array $assoc_args The associated arguments to pass to the command.
	 *
	 * @throws \phpDocumentor\Reflection\Exception\UnparsableFile
	 * @throws \phpDocumentor\Reflection\Exception\UnreadableFile
	 */
	public function create( $args, $assoc_args ) {
		list( $directory ) = $args;
		$directory = realpath( $directory );
		$ignore_files = empty( $assoc_args['ignore_files'] ) ? array() : explode( ',', $assoc_args['ignore_files'] );

		if ( empty( $directory ) ) {
			WP_CLI::error( sprintf( "Can't read %1\$s. Does the file exist?", $directory ) );
			exit;
		}

		WP_CLI::line();

		$data = $this->_get_phpdoc_data( $directory, 'array', $ignore_files );

		// Import data
		$this->_do_import( $data, isset( $assoc_args['quick'] ), isset( $assoc_args['import-internal'] ) );
	}

	/**
	 * Generate the data from the PHPDoc markup.
	 *
	 * @param string $path         Directory or file to scan for PHPDoc
	 * @param string $format       What format the data is returned in: [json|array].
	 * @param array  $ignore_files What files to ignore.
	 *
	 * @return string|array
	 * @throws \phpDocumentor\Reflection\Exception\UnparsableFile
	 * @throws \phpDocumentor\Reflection\Exception\UnreadableFile
	 */
	protected function _get_phpdoc_data( $path, $format = 'json', $ignore_files = array() ) {

		if ( USE_PLUGIN_PREFIX === true ) {
			// Determine whether this is a plugin we can parse.
			$plugin_finder = new PluginFinder( $path, $ignore_files );
			$plugin_finder->find();

			if ( ! $plugin_finder->is_valid_plugin() ) {
				WP_CLI::error( "Sorry, the directory you selected doesn't contain a valid Yoast plugin" );
				exit;
			}

			$runner = new Runner( $plugin_finder->get_plugin() );
		} else {
			$runner = new Runner();
		}

		WP_CLI::line( sprintf( 'Extracting PHPDoc from %1$s. This may take a few minutes...', $path ) );

		$is_file = is_file( $path );
		$files   = $is_file ? array( $path ) : Utils::get_files( $path, $ignore_files );
		$path    = $is_file ? dirname( $path ) : $path;

		if ( $files instanceof \WP_Error ) {
			WP_CLI::error( sprintf( 'Problem with %1$s: %2$s', $path, $files->get_error_message() ) );
			exit;
		}

		$output = $runner->parse_files( $files, $path );

		if ( $format === 'json' ) {
			return json_encode( $output, JSON_PRETTY_PRINT );
		}

		return $output;
	}

	/**
	 * Import the PHPDoc $data into WordPress posts and taxonomies
	 *
	 * @param array $data
	 * @param bool  $skip_sleep     If true, the sleep() calls are skipped.
	 * @param bool  $import_ignored If true, functions marked `@ignore` will be imported.
	 */
	protected function _do_import( array $data, $skip_sleep = false, $import_ignored = false ) {

		if ( ! wp_get_current_user()->exists() ) {
			WP_CLI::error( 'Please specify a valid user: --user=<id|login>' );
			exit;
		}

		// Run the importer
		$importer = new Importer();
		$importer->setLogger( new WP_CLI_Logger() );
		$importer->import( $data, $skip_sleep, $import_ignored );

		WP_CLI::line();
	}
}
