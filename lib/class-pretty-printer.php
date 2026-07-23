<?php

namespace WP_Parser;

/**
 * Extends default printer for arguments.
 */
class Pretty_Printer extends \phpDocumentor\Reflection\PrettyPrinter {
	/**
	 * Print names as they appeared before PHP-Parser's name resolution.
	 *
	 * PHP-Parser represents resolved global names as fully-qualified names. The
	 * leading namespace separator is useful in an AST, but adding it to exported
	 * source expressions changes the established JSON output.
	 *
	 * @param \PhpParser\Node\Name\FullyQualified $node Fully-qualified name.
	 *
	 * @return string Printed name.
	 */
	protected function pName_FullyQualified( \PhpParser\Node\Name\FullyQualified $node ): string {
		$name = $node->toString();

		return false === strpos( $name, '\\' ) ? $name : '\\' . $name;
	}

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
