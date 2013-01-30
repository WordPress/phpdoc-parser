<?php
define('QP_INCLUDE_BUILTIN', false);
require dirname(__FILE__) . '/library/class-utility.php';
require dirname(__FILE__) . '/library/class-phpdoc-parser.php';

$data = json_decode(file_get_contents('output.json'));
$filters = json_decode(file_get_contents('filters.json'));
?>
<html>
<head>
	<link href="http://localhost/work/Renku/cosmonaut/store/content/themes/roscosmos/bootstrap/css/bootstrap.css" rel="stylesheet" />
</head>
<body>
	<div class="container">
<?php

function esc_id($name) {
	$name = str_replace(array('"', "'"), '', $name);
	$name = str_replace(array('{', '}', '$', ' ', '.', '[', ']'), '_', $name);
	return $name;
}

$data->__main->doc = 'This is a placeholder for the global scope.';
$filters = (array) $filters->filters;

echo '<h1>' . count($filters) . ' actions and filters</h1>';

foreach ($filters as $name => $filter):
?>
	<article id="<?php echo esc_id($name) ?>" class="row">
		<h2><?php echo $name ?></h2>
		<p><?php echo $filter->callers[0]->type ?></p>
		<div class="span12">
			<h3>Used by</h3>
			<ul>
<?php
	foreach ($filter->callers as $caller) {
		echo '<li><a href="display.php#' . $caller->name .'">' . $caller->name . '()</a>: <code>' . htmlspecialchars($caller->source) .'</code></li>';
	}
?>
			</ul>
		</div>
	</article>
<?php
endforeach;
?>