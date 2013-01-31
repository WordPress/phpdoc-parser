<?php

class WP_Repo_Filters implements ArrayAccess {
	public function offsetGet($name) {
		if (!isset($this->filters[$name]))
			$this->filters[$name] = new WP_Filter($name);

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