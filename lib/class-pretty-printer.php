<?php

namespace WP_Parser;

use PhpParser\Node\Arg;

/**
 * Extends default printer for arguments.
 */
class Pretty_Printer extends \PhpParser\PrettyPrinter\Standard {
	/**
	 * Pretty prints an argument.
	 *
	 * @param PhpParser\Node\Arg $node Expression argument
	 *
	 * @return string Pretty printed argument
	 */
	public function prettyPrintArg( Arg $node ) {
		return str_replace( "\n" . $this->noIndentToken, "\n", $this->p( $node ) );
	}
}
