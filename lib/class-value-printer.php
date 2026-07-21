<?php

namespace WP_Parser;

use PhpParser\Node\Scalar\String_;

/**
 * Pretty printer for value expressions (argument defaults, constant values).
 *
 * Prints string scalars using their raw source representation instead of
 * the interpreted value, so escape sequences and quoting are preserved
 * exactly as written.
 */
class Value_Printer extends \PhpParser\PrettyPrinter\Standard {

	/**
	 * @param String_ $node
	 *
	 * @return string
	 */
	public function pScalar_String( String_ $node ): string {
		$original = $node->getAttribute( 'rawValue' );
		if ( null === $original ) {
			return parent::pScalar_String( $node );
		}

		return $original;
	}
}
