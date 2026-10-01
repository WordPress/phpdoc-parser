<?php

namespace WP_Parser;

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
	 *
	 * @param array $args
	 */
	public function export( $args ) {
		$directory   = realpath( $args[0] );
		$output_file = empty( $args[1] ) ? 'phpdoc.json' : $args[1];
		$json        = $this->_get_phpdoc_data( $directory );
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
	 * Imports an exported parser document into WordPress.
	 *
	 * The command validates the parsed-file envelope before invoking the importer
	 * and preserves setup Blueprint object and list shapes while decoding JSON.
	 *
	 * @synopsis <file> [--quick] [--import-internal]
	 *
	 * @param array $args
	 * @param array $assoc_args
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

		$phpdoc = json_decode( $phpdoc );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			WP_CLI::error( sprintf( "JSON in %1\$s can't be decoded :(", $file ) );
			exit;
		}
		if ( ! is_array( $phpdoc ) ) {
			WP_CLI::error( sprintf( 'JSON in %1$s must contain a top-level list of parsed files.', $file ) );
			exit;
		}
		preserve_json_object_shapes( $phpdoc );

		// The importer dereferences these fields before it can report malformed
		// input. Validate the file envelope here so bad JSON data produces one
		// actionable CLI error instead of array-offset warnings or a type error.
		foreach ( $phpdoc as $index => $parsed_file ) {
			if (
				! is_array( $parsed_file ) ||
				! isset( $parsed_file['path'], $parsed_file['file'] ) ||
				! is_string( $parsed_file['path'] ) ||
				'' === $parsed_file['path'] ||
				! is_array( $parsed_file['file'] ) ||
				! isset(
					$parsed_file['file']['description'],
					$parsed_file['file']['long_description'],
					$parsed_file['file']['tags']
				) ||
				! is_string( $parsed_file['file']['description'] ) ||
				! is_string( $parsed_file['file']['long_description'] ) ||
				! is_array( $parsed_file['file']['tags'] ) ||
				array_values( $parsed_file['file']['tags'] ) !== $parsed_file['file']['tags']
			) {
				WP_CLI::error(
					sprintf(
						'JSON in %1$s entry %2$d must contain a parsed file object with a path and file metadata.',
						$file,
						$index + 1
					)
				);
				exit;
			}
		}

		// Import data
		$this->_do_import( $phpdoc, isset( $assoc_args['quick'] ), isset( $assoc_args['import-internal'] ) );
	}

	/**
	 * Generate JSON containing the PHPDoc markup, convert it into WordPress posts, and insert into DB.
	 *
	 * @subcommand create
	 * @synopsis   <directory> [--quick] [--import-internal] [--user]
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function create( $args, $assoc_args ) {
		list( $directory ) = $args;
		$directory = realpath( $directory );

		if ( empty( $directory ) ) {
			WP_CLI::error( sprintf( "Can't read %1\$s. Does the file exist?", $directory ) );
			exit;
		}

		WP_CLI::line();

		// Import data
		$this->_do_import( $this->_get_phpdoc_data( $directory, 'array' ), isset( $assoc_args['quick'] ), isset( $assoc_args['import-internal'] ) );
	}

	/**
	 * Generate the data from the PHPDoc markup.
	 *
	 * @param string $path   Directory or file to scan for PHPDoc
	 * @param string $format What format the data is returned in: [json|array].
	 *
	 * @return string|array
	 */
	protected function _get_phpdoc_data( $path, $format = 'json' ) {
		WP_CLI::line( sprintf( 'Extracting PHPDoc from %1$s. This may take a few minutes...', $path ) );
		$is_file = is_file( $path );
		$files   = $is_file ? array( $path ) : get_wp_files( $path );
		$path    = $is_file ? dirname( $path ) : $path;

		if ( $files instanceof \WP_Error ) {
			WP_CLI::error( sprintf( 'Problem with %1$s: %2$s', $path, $files->get_error_message() ) );
			exit;
		}

		$output = parse_files( $files, $path );

		if ( 'json' == $format ) {
			$json = json_encode( $output, JSON_PRETTY_PRINT );

			if ( false === $json ) {
				WP_CLI::error( sprintf( 'Problem encoding the data from %1$s as JSON: %2$s', $path, json_last_error_msg() ) );
				exit;
			}

			return $json;
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
		$importer = new Importer;
		$importer->setLogger( new WP_CLI_Logger() );
		$importer->import( $data, $skip_sleep, $import_ignored );

		WP_CLI::line();
	}
}
