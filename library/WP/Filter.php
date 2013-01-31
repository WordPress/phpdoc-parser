<?php

class WP_Filter {
	public $name = '';

	public $callers = array();

	public function __construct($name) {
		$this->name = $name;
	}
}