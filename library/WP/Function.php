<?php

class WP_Function {
	public $doc = '';
	public $file = '';
	public $line = 0;

	public $missingDoc = false;

	public $filters = array();
	public $uses = array();
	public $used_by = array();
}