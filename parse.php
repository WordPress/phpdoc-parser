#!/usr/bin/env php
<?php

require 'vendor/autoload.php';
require 'lib/WP/runner.php';

use phpDocumentor\Reflection\FileReflector;

if ($_SERVER['argc'] < 2)
	die("Please provide a directory to scan.\n");

$wp_dir = $_SERVER['argv'][1];

$files = get_wp_files($wp_dir);
$output = parse_files($files, $wp_dir);

echo json_encode($output, defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0);
