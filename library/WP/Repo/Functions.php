<?php

class WP_Repo_Functions implements ArrayAccess {
	public function offsetGet($name) {
		if (!isset($this->functions[$name]))
			$this->functions[$name] = new WP_Function($name);

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