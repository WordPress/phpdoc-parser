<?php

/**
 * A reflection class for a method call.
 */

namespace WP_Parser;

use phpDocumentor\Reflection\BaseReflector;

/**
 * A reflection of a method call expression.
 */
class Method_Call_Reflector extends BaseReflector {

	/**
	 * The class that this method was called in, if it was called in a class.
	 *
	 * @var \phpDocumentor\Reflection\ClassReflector|false
	 */
	protected $called_in_class = false;

	/**
	 * Returns the name for this Reflector instance.
	 *
	 * @return string[] Index 0 is the calling instance, 1 is the method name.
	 */
	public function getName() {
		$name = $this->getShortName();

		$printer = new Pretty_Printer;
		$caller = $printer->prettyPrintExpr( $this->node->var );

		if ( $this->called_in_class && '$this' === $caller ) {
			$caller = $this->called_in_class->getShortName();
		}

		return array( $caller, $name );
	}

	/**
	 * Set the class that this method was called within.
	 *
	 * @param \phpDocumentor\Reflection\ClassReflector $class
	 */
	public function set_class( \phpDocumentor\Reflection\ClassReflector $class ) {

		$this->called_in_class = $class;
	}
}
