<?php

set_error_handler( function ( $errno, $errstr, $errfile, $errline ) {
	return true;
}, E_DEPRECATED );

require __DIR__ . '/vendor/autoload.php';

class WP_CLI_Command {}

class WP_CLI {
	public static function line() {}
}

require __DIR__ . '/lib/class-command.php';
foreach ( glob( __DIR__ . '/lib/*.php' ) as $import ) {
	require_once $import;
}