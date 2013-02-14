#!/usr/bin/env php
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
	$output = array();

	foreach ($files as $filename) {
		$file = new WP_Reflection_FileReflector($filename);

		$path = ltrim(substr($filename, strlen($root)), DIRECTORY_SEPARATOR);
		$file->setFilename($path);

		$file->process();

		// TODO proper exporter
		$out = array(
			'path' => $file->getFilename(),
		);

		foreach ($file->getIncludes() as $include) {
			$out['includes'][] = array(
				'name' => $include->getName(),
				'line' => $include->getLineNumber(),
				'type' => $include->getType(),
			);
		}

		foreach ($file->getConstants() as $constant) {
			$out['constants'][] = array(
				'name' => $constant->getShortName(),
				'line' => $constant->getLineNumber(),
				'value' => $constant->getValue(),
			);
		}

		$hooks = export_hooks($file->hooks);
		if (!empty($hooks))
			$out['hooks'] = $hooks;

		foreach ($file->getFunctions() as $function) {
			$func = array(
				'name' => $function->getShortName(),
				'line' => $function->getLineNumber(),
				'arguments' => export_arguments($function->getArguments()),
			);

			$docblock = export_docblock($function);
			if ($docblock)
				$func['doc'] = $docblock;

			if (!empty($function->hooks))
				$func['hooks'] = export_hooks($function->hooks);

			$out['functions'][] = $func;
		}

		foreach ($file->getClasses() as $class) {
			$cl = array(
				'name' => $class->getShortName(),
				'line' => $class->getLineNumber(),
				'final' => $class->isFinal(),
				'abstract' => $class->isAbstract(),
				'extends' => $class->getParentClass(),
				'implements' => $class->getInterfaces(),
				'properties' => export_properties($class->getProperties()),
				'methods' => export_methods($class->getMethods()),
			);

			$docblock = export_docblock($class);
			if ($docblock)
				$cl['doc'] = $docblock;

			$out['classes'][] = $cl;
		}

		$output[] = $out;
	}

	return $output;
}

function export_docblock($element) {
	$docblock = $element->getDocBlock();
	if (!$docblock)
		return false;

	$output = array(
		'description' => $docblock->getShortDescription(),
		'long_description' => $docblock->getLongDescription()->getFormattedContents(),
	);
	$output['tags'] = array();
	foreach ($docblock->getTags() as $tag) {
		$output['tags'][] = array(
			'name' => $tag->getName(),
			'content' => $tag->getContent(),
		);
	}

	// TODO output tags

	return $output;
}

function export_hooks(array $hooks) {
	$out = array();
	foreach ($hooks as $hook) {
		$out[] = array(
			'name' => $hook->getName(),
			'line' => $hook->getLineNumber(),
			'type' => $hook->getType(),
			'arguments' => implode(', ', $hook->getArgs()),
		);
	}
	return $out;
}

function export_arguments(array $arguments) {
	$output = array();
	foreach ($arguments as $argument) {
		$output[] = array(
			'name' => $argument->getName(),
			'default' => $argument->getDefault(),
			'type' => $argument->getType(),
		);
	}
	return $output;
}

function export_properties(array $properties) {
	$out = array();
	foreach ($properties as $property) {
		$prop = array(
			'name' => $property->getName(),
			'line' => $property->getLineNumber(),
			'default' => $property->getDefault(),
			'final' => $property->isFinal(),
			'static' => $property->isStatic(),
			'visibililty' => $property->getVisibility(),
		);

		$docblock = export_docblock($property);
		if ($docblock)
			$prop['doc'] = $docblock;

		$out[] = $prop;

	}
	return $out;
}

function export_methods(array $methods) {
	$out = array();
	foreach ($methods as $method) {
		$meth = array(
			'name' => $method->getShortName(),
			'line' => $method->getLineNumber(),
			'final' => $method->isFinal(),
			'abstract' => $method->isAbstract(),
			'static' => $method->isStatic(),
			'visibility' => $method->getVisibility(),
			'arguments' => export_arguments($method->getArguments()),
		);

		$docblock = export_docblock($method);
		if ($docblock)
			$meth['doc'] = $docblock;

		if (!empty($method->hooks))
			$meth['hooks'] = export_hooks($method->hooks);

		$out[] = $meth;
	}
	return $out;
}

$files = get_wp_files($wp_dir);
$output = parse_files($files, $wp_dir);

echo json_encode($output, defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0);
