<?php

require dirname(__FILE__) . '/PHP-Parser/lib/PHPParser/Autoloader.php';
PHPParser_Autoloader::register();
require dirname(__FILE__) . '/library/QP/NodeVisitor.php';

$wp_dir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'trunk-git' . DIRECTORY_SEPARATOR . 'wp-includes';

class QP_CallingCard {
	public $caller = array();
	public $callee = '';
	public $line = 0;
	public $code = '';
	public $filterName = '';
	public $filterParts = array();
	public $args = array();
}

class QP_Filter {
	public $name = '';

	public $callers = array();

	public function __construct($name) {
		$this->name = $name;
	}
}

class QP_Caller {
	public $name = '';
	public $file = '';
	public $line = 0;
	public $type = 'unknown';
}

class QP_Function {
	public $doc = '';
	public $file = '';
	public $line = 0;

	public $missingDoc = false;

	public $filters = array();
	public $uses = array();
	public $used_by = array();
}

class QP_Repo_Functions implements ArrayAccess {
	public function offsetGet($name) {
		if (!isset($this->functions[$name]))
			$this->functions[$name] = new QP_Function($name);

		return $this->functions[$name];
	}

	public function offsetExists($name) {
		return true;
	}

	public function offsetSet($name, $value) {
		throw new Exception('Cannot set function');
	}

	public function offsetUnset($name) {
		unset($this->functions[$name]);
	}
}

class QP_Repo_Filters implements ArrayAccess {
	public function offsetGet($name) {
		if (!isset($this->filters[$name]))
			$this->filters[$name] = new QP_Filter($name);

		return $this->filters[$name];
	}

	public function offsetExists($name) {
		return true;
	}

	public function offsetSet($name, $value) {
		throw new Exception('Cannot set function');
	}

	public function offsetUnset($name) {
		unset($this->filters[$name]);
	}
}


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
header('Content-Type: text/plain');

$parser = new PHPParser_Parser(new PHPParser_Lexer);
$repository = new QP_Repo_Functions;
$filters = new QP_Repo_Filters;
$files = get_wp_files($wp_dir);

foreach ($files as $file) {
	$code = file_get_contents($file);
	$file = str_replace($wp_dir . DIRECTORY_SEPARATOR, '', $file);
	$file = str_replace(DIRECTORY_SEPARATOR, '/', $file);
	try {
		$stmts = $parser->parse($code);
	}
	catch (PHPParser_Error $e) {
		echo $file . "\n";
		echo $e->getMessage();
		die();
	}

	$traverser = new PHPParser_NodeTraverser;
	$visitor = new QP_NodeVisitor($file, $repository, $filters);
	$traverser->addVisitor($visitor);
	$stmts = $traverser->traverse($stmts);
}

$functions = array_filter($repository->functions, function ($details) {
	// Built-in function
	return !empty($details->file) || !empty($details->uses);
});

try {
	file_put_contents('output.json', json_encode($functions));
	file_put_contents('filters.json', json_encode($filters->filters));
}
catch (PHPParser_Error $e) {
	echo $e->getMessage();
}