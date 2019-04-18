<?php namespace WP_Parser\Reflectors;
/**
 * A reflection class for a function call.
 */

use phpDocumentor\Reflection\BaseReflector;

/**
 * A reflection of a function call expression.
 */
class Function_Reflector extends BaseReflector {

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

		if ( is_a( $shortName, 'PHPParser_Node_Name_FullyQualified' ) ) {
			return '\\' . (string) $shortName;
		}

		if ( is_a( $shortName, 'PHPParser_Node_Name' ) ) {
			return (string) $shortName;
		}

		/** @var \PHPParser_Node_Expr_ArrayDimFetch $shortName */
		if ( is_a( $shortName, 'PHPParser_Node_Expr_ArrayDimFetch' ) ) {
			$var = $shortName->var->name;
			$dim = $shortName->dim->name->parts[0];

			return "\${$var}[{$dim}]";
		}

		/** @var \PHPParser_Node_Expr_Variable $shortName */
		if ( is_a( $shortName, 'PHPParser_Node_Expr_Variable' ) ) {
			return $shortName->name;
		}

		return (string) $shortName;
	}
}
