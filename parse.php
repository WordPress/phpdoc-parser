<?php

require 'vendor/autoload.php';

use phpDocumentor\Reflection\FileReflector;

if ($_SERVER['argc'] < 2)
	die("Please provide a directory to scan.\n");

$wp_dir = $_SERVER['argv'][1];

function get_wp_files($directory) {
	$iterableFiles =  new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($directory)
	);
	$files = array();
	try {
		foreach( $iterableFiles as $file ) {
			if ($file->getExtension() !== 'php')
				continue;

			if ($file->getFilename() === 'class-wp-json-server.php')
				continue;

			$files[] = $file->getPathname();
		}
	}
	catch (UnexpectedValueException $e) {
		printf("Directory [%s] contained a directory we can not recurse into", $directory);
	}

	return $files;
}

function parse_files($files, $root) {
	$functions = array();

	foreach ($files as $filename) {
		$file = new WP_Reflection_FileReflector($filename);

		$path = ltrim(substr($filename, strlen($root)), DIRECTORY_SEPARATOR);
		$file->setFilename($path);

		$file->process();

		// TODO proper exporter

		print $file->getFilename() . "\n";
		if (!empty($file->hooks)) {
			foreach ($file->hooks as $hook) {
				print '  ' . $hook->getName() . "\n";
			}
		}

		foreach ($file->getFunctions() as $function) {
			print '  ' . $function->getShortName() . "\n";
			if (!empty($function->hooks)) {
				foreach ($function->hooks as $hook) {
					print '    ' . $hook->getName() . "\n";
				}
			}
		/*
			$functions[$file->getFilename()][] = array(
				'name' => $function->getShortName(),
				'line' => $function->getLineNumber(),
			);
		 */
		}
	}

	return $functions;
}

$files = get_wp_files($wp_dir);
$functions = parse_files($files, $wp_dir);

file_put_contents('output.json', json_encode($functions));
