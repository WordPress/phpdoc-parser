<?php

$global_error = false;
set_error_handler( function ( $errno, $errstr, $errfile, $errline ) use ( &$global_error ) {
	$global_error = true;
	if ( E_DEPRECATED === $errno ) {
		return true;
	}

	$errors = [ 'E_NONE', 'E_ERROR', 'E_WARNING', 'E_PARSE', 'E_NOTICE', 'E_CORE_ERROR', 'E_CORE_WARNING', 'E_COMPILE_ERROR' ];

	echo "\e[33m{$errors[ $errno ]}\e[90m:\e[31m{$errstr}\e[m\n";
	echo "\e[90mat \e[34m{$errfile}\e[90m:\e[33m{$errline}\e[m\n";
	echo "\e[90m";
	debug_print_backtrace();
	echo "\e[m\n";

	return true;
}, E_ALL );

require __DIR__ . '/vendor/autoload.php';

class WP_CLI_Command {}

class WP_CLI {
	public static function line() {}
}

require __DIR__ . '/lib/class-command.php';
foreach ( glob( __DIR__ . '/lib/*.php' ) as $import ) {
	require_once $import;
}