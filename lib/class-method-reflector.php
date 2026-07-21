<?php

namespace WP_Parser;

/**
 * A reflection of a class method declaration.
 */
class Method_Reflector extends Function_Reflector {

	/**
	 * @var \PhpParser\Node\Stmt\ClassMethod
	 */
	protected $node;

	/**
	 * Returns the visibility of this method: 'public', 'protected', or
	 * 'private'.
	 *
	 * @return string
	 */
	public function getVisibility() {
		if ( $this->node->isProtected() ) {
			return 'protected';
		}

		if ( $this->node->isPrivate() ) {
			return 'private';
		}

		return 'public';
	}

	/**
	 * @return bool
	 */
	public function isAbstract() {
		return $this->node->isAbstract();
	}

	/**
	 * @return bool
	 */
	public function isStatic() {
		return $this->node->isStatic();
	}

	/**
	 * @return bool
	 */
	public function isFinal() {
		return $this->node->isFinal();
	}
}
