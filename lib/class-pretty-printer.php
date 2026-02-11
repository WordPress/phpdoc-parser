<?php

namespace WP_Parser;

use PHPParser\Node\Arg;
use PHPParser\PrettyPrinter\Standard;

/**
 * Extends default printer for arguments.
 */
class Pretty_Printer extends Standard {
	/**
	 * Pretty prints an argument.
	 *
	 * @param PHPParser\Node\Arg $node Expression argument
	 *
	 * @return string Pretty printed argument
	 */
	public function prettyPrintArg( Arg $node ) {
		return str_replace( "\n" . $this->noIndentToken, "\n", $this->p( $node ) );
	}
}
