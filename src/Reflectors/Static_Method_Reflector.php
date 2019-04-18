<?php namespace WP_Parser\Reflectors;

/**
 * A reflection of a method call expression.
 */
class Static_Method_Reflector extends Method_Reflector {

	/**
	 * Returns the name for this Reflector instance.
	 *
	 * @return string[] Index 0 is the class name, 1 is the method name.
	 */
	public function getName() {
		$class = $this->node->class;
		$prefix = ( is_a( $class, 'PHPParser_Node_Name_FullyQualified' ) ) ? '\\' : '';
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
