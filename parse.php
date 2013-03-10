#!/usr/bin/env php
<?php

require 'vendor/autoload.php';
require 'lib/WP/runner.php';

use phpDocumentor\Reflection\FileReflector;

if ($_SERVER['argc'] < 2)
	die("Please provide a directory or file to scan.\n");

$wp_dir = $_SERVER['argv'][1];

// Find the files to get the PHPDoc data from. $wp_dir can either be a folder or an absolute ref to a file.
if ( is_file( $wp_dir ) ) {
	$files  = array( $wp_dir );
	$wp_dir = dirname( $path );

} else {
	$files = get_wp_files( $wp_dir );
}

$output = parse_files($files, $wp_dir);

echo json_encode($output, defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0);
