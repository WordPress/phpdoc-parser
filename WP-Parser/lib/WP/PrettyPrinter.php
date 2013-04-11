<?php

class WP_PrettyPrinter extends PHPParser_PrettyPrinter_Default {
	/**
	 * Pretty prints an argument.
	 *
	 * @param PHPParser_Node_Arg $node Expression argument
	 *
	 * @return string Pretty printed argument
	 */
	public function prettyPrintArg(PHPParser_Node_Arg $node) {
		return str_replace("\n" . $this->noIndentToken, "\n", $this->p($node));
	}
}
