<?php

$query = new WP_Query();

function test() {
	$a = new My_Class;
}

class My_Class extends Parent_Class {

	static function instance() {
		return new self;
	}

	static function parent() {
		return new parent;
	}
}

$b = new $class;
