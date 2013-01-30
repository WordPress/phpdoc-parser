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

echo '<h1>' . count((array) $data) . ' functions and methods</h1>';

foreach ($data as $name => $function):
	$doc = Codex_Generator_Phpdoc_Parser::parse_doc($function->doc);
?>
	<article id="<?php echo esc_id($name) ?>" class="row">
		<h2><?php echo $name ?>()</h2>
		<div>
			<div class="well">
				<p><?php echo $doc['short_desc'] ?></p>
				<p><?php echo htmlspecialchars($doc['long_desc']) ?></p>
			</div>
			<ul>
<?php
	foreach ($doc['tags'] as $tag => $value):
		if (empty($value))
			continue;
?>
				<li><strong><?php echo ucfirst($tag) ?></strong>: <?php
		if (is_array($value)) {
			echo '<ul><li>' . implode('</li><li>', $value) . '</li></ul>';
		}
		else
			echo htmlspecialchars($value);
		?></li>
<?php
	endforeach;
?>
			</ul>
			<p><a href="http://core.trac.wordpress.org/browser/tags/3.5/wp-includes/<?php echo $function->file . '#L' . $function->line
				?>">View source (<code><?php echo $function->file ?></code> @ L<?php echo $function->line ?>)</a></p>
		</div>
		<div class="span6">
			<h3>Filters</h3>
			<ul>
<?php
	foreach ($function->filters as $filter) {
		echo '<li><a href="filters.php#' . esc_id($filter) .'">' . $filter . '</a></li>';
	}
?>
			</ul>
		</div>
		<div class="span3">
<?php
	if (!empty($function->used_by)):
?>
			<h3>Used by</h3>
			<ul>
<?php
		foreach ($function->used_by as $other => $_) {
			if (empty($data->$other) && !QP_INCLUDE_BUILTIN)
				continue;

			if (empty($data->$other))
				echo '<li><a href="http://php.net/' . $other . '">' . $other . '()</a></li>';
			else
				echo '<li><a href="#' . esc_id($other) . '">' . $other . '()</a></li>';
		}
?>
			</ul>
		</div>
<?php
	endif;
	if (!empty($function->uses)):
?>
		<div class="span3">
			<h3>Uses</h3>
			<ul>
<?php
		foreach ($function->uses as $other => $_) {
			if (empty($data->$other) && !QP_INCLUDE_BUILTIN)
				continue;
			if (empty($data->$other))
				echo '<li><a href="http://php.net/' . $other . '">' . $other . '()</a></li>';
			else
				echo '<li><a href="#' . esc_id($other) . '">' . $other . '()</a></li>';
		}
?>
			</ul>
		</div>
<?php
	endif;
	echo '</article>';
endforeach;
?>