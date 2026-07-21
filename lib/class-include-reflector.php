<?php

namespace WP_Parser;

use PhpParser\Node\Expr\Include_;

/**
 * A reflection of an include/require expression.
 */
class Include_Reflector extends Abstract_Reflector {

	/**
	 * @var Include_
	 */
	protected $node;

	/**
	 * Returns the type of this include: 'Include', 'Include Once',
	 * 'Require', or 'Require Once'.
	 *
	 * @return string
	 */
	public function getType() {
		switch ( $this->node->type ) {
			case Include_::TYPE_INCLUDE:
				return 'Include';
			case Include_::TYPE_INCLUDE_ONCE:
				return 'Include Once';
			case Include_::TYPE_REQUIRE:
				return 'Require';
			case Include_::TYPE_REQUIRE_ONCE:
				return 'Require Once';
			default:
				throw new \RuntimeException( 'Unknown include type detected: ' . $this->node->type );
		}
	}

	/**
	 * Returns the included path when it is a string literal, otherwise an
	 * empty string.
	 *
	 * @return string
	 */
	public function getShortName() {
		return isset( $this->node->expr->value )
			? (string) $this->node->expr->value
			: '';
	}
}
