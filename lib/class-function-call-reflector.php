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

		if ( is_a( $shortName, 'PHPParser\Node\Name\FullyQualified' ) ) {
			return '\\' . (string) $shortName;
		}

		if ( is_a( $shortName, 'PHPParser\Node\Name' ) ) {
			return (string) $shortName;
		}

		/** @var \PHPParser\Node\Expr\ArrayDimFetch $shortName */
		if ( is_a( $shortName, 'PHPParser\Node\Expr\ArrayDimFetch' ) ) {
			$var = $shortName->var->name;
			$dim = $shortName->dim->name->parts[0];

			return "\${$var}[{$dim}]";
		}

		/** @var \PHPParser\Node\Expr\Variable $shortName */
		if ( is_a( $shortName, 'PHPParser\Node\Expr\Variable' ) ) {
			return $shortName->name;
		}

		return (string) $shortName;
	}
}
