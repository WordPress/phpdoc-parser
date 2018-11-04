<?php

/**
 * A reflection class for a function call.
 */

namespace WP_Parser;

use phpDocumentor\Reflection\BaseReflector;

/**
 * A reflection of a function call expression.
 */
class Function_Call_Reflector extends BaseReflector {

	/**
	 * Returns the name for this Reflector instance.
	 *
	 * @return string
	 */
	public function getName() {
		if ( isset( $this->node->namespacedName ) ) {
			return '\\' . implode( '\\', $this->node->namespacedName->parts );
		}

		$shortName = $this->getShortName();

		if ( is_a( $shortName, 'PhpParser\Node\Name\FullyQualified' ) ) {
			return '\\' . (string) $shortName;
		}

		if ( is_a( $shortName, 'PhpParser\Node\Name' ) ) {
			return (string) $shortName;
		}

		/** @var \PhpParser\Node\Expr\ArrayDimFetch $shortName */
		if ( is_a( $shortName, 'PhpParser\Node\Expr\ArrayDimFetch' ) ) {
			$var = $shortName->var->name;
			$dim = $shortName->dim->name->parts[0];

			return "\${$var}[{$dim}]";
		}

		/** @var \PhpParser\Node\Expr\Variable $shortName */
		if ( is_a( $shortName, 'PhpParser\Node\Expr\Variable' ) ) {
			return $shortName->name;
		}

		return (string) $shortName;
	}
}
