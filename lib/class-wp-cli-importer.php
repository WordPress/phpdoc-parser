<?php

namespace WP_Parser;

use WP_CLI;

/**
 * Importer for using with WP-CLI.
 */
class WP_CLI_Importer extends Importer {

	/**
	 * @param string $message
	 */
	public function log( $message ) {
		WP_CLI::log( $message );
	}

	/**
	 * @param string $message
	 */
	public function warn( $message ) {
		WP_CLI::warning( $message );
	}

	/**
	 * @param string $message
	 */
	public function error( $message ) {
		WP_CLI::error( $message );
	}

	/**
	 * @param string $message
	 */
	public function success( $message ) {
		WP_CLI::success( $message );
	}
}
