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
	public function getName(): array {
		$class = $this->node->class;
		$prefix = ( is_a( $class, 'PhpParser\Node\Name\FullyQualified' ) ) ? '\\' : '';

		if ( $class instanceof \PhpParser\Node\Stmt\Class_ && $class->isAnonymous() ) {
			$class = 'class@anonymous';
		} elseif ( $class instanceof \PhpParser\Node\Expr\Variable ) {
			// Static calls like `$foo::bar()`
			$class = '$' . $this->nameToString( $class->name );
		} else {
			$class = $prefix . $this->_resolveName( $this->nameToString( $class ) );
		}

		return array( $class, $this->getShortName() );
	}

	/**
	 * @return bool
	 */
	public function isStatic() {
		return true;
	}
}
