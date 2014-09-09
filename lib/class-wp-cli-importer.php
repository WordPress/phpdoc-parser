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

	/**
	 * Log a messsage.
	 *
	 * @param string The message to log.
	 */
	public function log( $message ) {
		WP_CLI::log( $message );
	}

	/**
	 * Give a warning.
	 *
	 * @param string The warning message.
	 */
	public function warn( $message ) {
		WP_CLI::warning( $message );
	}
}

// EOF
