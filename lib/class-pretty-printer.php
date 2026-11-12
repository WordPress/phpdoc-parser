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
	 * Single-segment fully-qualified names are therefore printed without the
	 * leading backslash. Inside a namespaced file the printed form denotes a
	 * namespaced symbol rather than the global one, for example `\Foo::BAR` is
	 * printed as `Foo::BAR`, which in a namespaced file would resolve to
	 * `Vendor\Foo::BAR`. This is an accepted limitation because the parser
	 * targets global-namespace WordPress core code.
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
	 * Print heredoc and nowdoc strings with their delimiters.
	 *
	 * The parent printer returns PHP-Parser's `rawValue` attribute so that
	 * escape sequences are not interpreted. For heredoc and nowdoc strings that
	 * attribute holds the body only, without the `<<<LABEL` delimiters, which is
	 * no longer a PHP expression. Those are printed by the default printer,
	 * which does not interpret escape sequences in doc strings either.
	 *
	 * @param \PhpParser\Node\Scalar\String_ $node String.
	 *
	 * @return string Printed string.
	 */
	public function pScalar_String( \PhpParser\Node\Scalar\String_ $node ): string {
		$kind = $node->getAttribute( 'kind' );

		if (
			\PhpParser\Node\Scalar\String_::KIND_HEREDOC === $kind ||
			\PhpParser\Node\Scalar\String_::KIND_NOWDOC === $kind
		) {
			return \PhpParser\PrettyPrinter\Standard::pScalar_String( $node );
		}

		return parent::pScalar_String( $node );
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
