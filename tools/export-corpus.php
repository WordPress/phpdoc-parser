<?php

/**
 * Exports parsed documentation JSON for a corpus of PHP files.
 *
 * Runs under plain PHP, without WordPress: the exporter in lib/runner.php is a
 * side-effect-free function library loaded by the Composer autoloader.
 *
 * The parser root is a parameter so one copy of this script can drive two
 * checkouts of the parser (base and head) over the same corpus.
 *
 * Usage:
 *
 *     php -d memory_limit=4G tools/export-corpus.php <parser-root> <corpus-dir> > corpus.json
 */

// Keep stdout pure JSON: vendored code emits deprecation notices on newer
// PHP, and display_errors otherwise interleaves them with the output.
ini_set( 'display_errors', 'stderr' );

if ( $argc < 3 ) {
	fwrite( STDERR, 'Usage: php tools/export-corpus.php <parser-root> <corpus-dir>' . PHP_EOL );
	exit( 1 );
}

$parser_root = rtrim( $argv[1], '/' );
$corpus_dir  = rtrim( $argv[2], '/' );

if ( ! is_file( $parser_root . '/vendor/autoload.php' ) ) {
	fwrite( STDERR, 'No Composer autoloader in ' . $parser_root . '; run composer install there first.' . PHP_EOL );
	exit( 1 );
}

if ( ! is_dir( $corpus_dir ) ) {
	fwrite( STDERR, 'Corpus directory not found: ' . $corpus_dir . PHP_EOL );
	exit( 1 );
}

require $parser_root . '/vendor/autoload.php';

$files = \WP_Parser\get_wp_files( $corpus_dir );

if ( ! is_array( $files ) ) {
	fwrite( STDERR, 'Could not list the PHP files in ' . $corpus_dir . '.' . PHP_EOL );
	exit( 1 );
}

echo json_encode( \WP_Parser\parse_files( $files, $corpus_dir ), JSON_PRETTY_PRINT ), PHP_EOL;
