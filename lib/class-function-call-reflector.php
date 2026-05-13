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
			return '\\' . $this->nameToString( $this->node->namespacedName );
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
			$var = $this->nameToString( $shortName->var->name );
			$dim = isset( $shortName->dim->name )
				? $this->nameToString( $shortName->dim->name )
				: ( isset( $shortName->dim->value ) ? $shortName->dim->value : (string) $shortName->dim );

			return "\${$var}[{$dim}]";
		}

		/** @var \PhpParser\Node\Expr\Variable $shortName */
		if ( is_a( $shortName, 'PhpParser\Node\Expr\Variable' ) ) {
			return $this->nameToString( $shortName->name );
		}

		/** @var \PhpParser\Node\Expr\PropertyFetch $shortName */
		if ( is_a( $shortName, 'PhpParser\Node\Expr\PropertyFetch' ) ) {
			return sprintf(
				'($%s->%s)',
				$this->nameToString( $shortName->var->name ),
				$this->nameToString( $shortName->name )
			);
		}

		return (string) $shortName;
	}
}
