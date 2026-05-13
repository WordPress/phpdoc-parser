<?php

namespace WP_Parser;

/**
 * Extends default printer for arguments.
 */
class Pretty_Printer extends \PhpParser\PrettyPrinter\Standard {
	/**
	 * Pretty prints an argument.
	 *
	 * @param \PhpParser\Node\Arg $node Expression argument
	 *
	 * @return string Pretty printed argument
	 */
	public function prettyPrintArg( \PhpParser\Node\Arg $node ) {
		$printed = $this->p( $node );

		if ( property_exists( $this, 'noIndentToken' ) ) {
			return str_replace( "\n" . $this->noIndentToken, "\n", $printed );
		}

		return $printed;
	}
}
