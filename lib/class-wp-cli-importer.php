<?php

/**
 * Importer class for use with WP-CLI.
 *
 * @package WP_Parser
 */

namespace WP_Parser;

use WP_CLI;

/**
 * Importer for using with WP-CLI.
 */
class WP_CLI_Importer extends Importer {

	public function log( $message ) {
		WP_CLI::log( $message );
	}

	public function warn( $message ) {
		WP_CLI::warning( $message );
	}

	public function error( $message ) {
		WP_CLI::error( $message );
	}

	public function success( $message ) {
		WP_CLI::success( $message );
	}
}

// EOF
