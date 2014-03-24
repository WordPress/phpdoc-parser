<?php

/**
 * A reflection class for a function call.
 */

namespace WP_Parser;

use phpDocumentor\Reflection\BaseReflector;
use phpDocumentor\Reflection\DocBlock\Context;

/**
 * A reflection of a function call expression.
 */
class Function_Call_Reflector extends BaseReflector {

	/**
	 * Initializes the reflector using the function statement object of
	 * PHP-Parser.
	 *
	 * @param \PHPParser_Node_Expr_FuncCall              $node    Function object
	 *                                                            coming from PHP-Parser.
	 * @param \phpDocumentor\Reflection\DocBlock\Context $context The context in
	 *                                                            which the node occurs.
	 */
	public function __construct( \PHPParser_Node_Expr $node, Context $context ) {
		parent::__construct( $node, $context );
	}

	/**
	 * Returns the name for this Reflector instance.
	 *
	 * @return string
	 */
	public function getName() {
		if (isset($this->node->namespacedName)) {
			return '\\'.implode('\\', $this->node->namespacedName->parts);
		}

		return (string) $this->getShortName();
	}
}
