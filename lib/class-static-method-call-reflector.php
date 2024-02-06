<?php

namespace WP_Parser;

/**
 * A reflection of a method call expression.
 */
class Static_Method_Call_Reflector extends Method_Call_Reflector {

	/**
	 * Returns the name for this Reflector instance.
	 *
	 * @return string[] Index 0 is the class name, 1 is the method name.
	 */
	public function getName() {
		$class = $this->node->class;
		$prefix = ( is_a( $class, 'PhpParser\Node\Name\FullyQualified' ) ) ? '\\' : '';
		$class = $prefix . $this->_resolveName( implode( '\\', $class->parts ) );

		return array( $class, $this->getShortName() );
	}

	/**
	 * @return bool
	 */
	public function isStatic() {
		return true;
	}
}
