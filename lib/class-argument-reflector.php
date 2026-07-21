<?php

namespace WP_Parser;

use PhpParser\Node\Param;

/**
 * A reflection of a function or method argument.
 */
class Argument_Reflector extends Abstract_Reflector {

	/**
	 * @var Param
	 */
	protected $node;

	/**
	 * Returns the argument name, including the `$` sigil.
	 *
	 * @return string
	 */
	public function getName() {
		return '$' . parent::getName();
	}

	/**
	 * Returns the source representation of the default value, or null if
	 * the argument has no default.
	 *
	 * @return string|null
	 */
	public function getDefault() {
		if ( ! $this->node->default ) {
			return null;
		}

		return $this->getRepresentationOfValue( $this->node->default );
	}

	/**
	 * Returns the type declaration as a string, or an empty string if the
	 * argument has no type.
	 *
	 * @return string
	 */
	public function getType() {
		return $this->typeToString( $this->node->type );
	}
}
