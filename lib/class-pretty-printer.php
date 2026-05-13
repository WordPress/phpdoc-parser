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
		$printed = '';

		if ( null !== $node->name ) {
			$printed .= $node->name->toString() . ': ';
		}

		if ( $node->byRef ) {
			$printed .= '&';
		}

		if ( $node->unpack ) {
			$printed .= '...';
		}

		$printed .= $this->prettyPrintExpr( $node->value );

		return $printed;
	}
}
