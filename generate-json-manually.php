<?php

/**
 * Parses a directory and generates the export JSON blob to import into
 * the docs theme/site. Runs ideally with the version of PHP specified
 * in {@see composer.json}.
 *
 * Example:
 *
 *     php generate-json-manually.php -d ~/wordpress-develop/src/ -o wp-6.8-docs.json
 */

$opts = getopt( 'd:o:' );
if ( ! isset( $opts['d'], $opts['o'] ) ) {
	echo <<<'USAGE'
Usage: php generate-json-manually.php -d [path] -o [filename]

  Options:

    -d Parse the source code in this directory
       e.g. "~/wordpress-develop/src/"

    -o Output filename
       e.g. "wp-6.8-docs.json"

  Notes:

    Parsing may require more than the default memory limit for PHP.
    In such a case, run PHP with a higher limit, either by updating
    the `php.ini` file, or by running with `php -dmemory_limit=4g`.


USAGE;
	die(1);
}

require __DIR__ . '/vendor/autoload.php';

// Polyfill some WP CLI classes.
class WP_CLI_Command {}

class WP_CLI {
	public static function line() {}
}

// Import the project.
require __DIR__ . '/lib/class-command.php';
foreach ( glob( __DIR__ . '/lib/*.php' ) as $import ) {
	require_once $import;
}

class ManualRunner extends WP_Parser\Command {
	public static function generate( $path ) {
		$docs_parser = new parent();

		return $docs_parser->_get_phpdoc_data( $path );
	}
}

$f = fopen( $opts['o'], "w" );

fwrite( $f, ManualRunner::generate( $opts['d'] ) );
