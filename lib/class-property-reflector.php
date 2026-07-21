<?php

namespace WP_Parser;

use phpDocumentor\Reflection\DocBlock\Context;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;

/**
 * A reflection of a class property declaration.
 *
 * A single property statement may declare multiple properties; each gets
 * its own reflector sharing the statement for modifiers and docblock.
 */
class Property_Reflector extends Abstract_Reflector {

	/**
	 * The property statement holding modifiers and the docblock.
	 *
	 * @var Property
	 */
	protected $property;

	/**
	 * @var PropertyProperty
	 */
	protected $node;

	/**
	 * @param Property         $property
	 * @param Context          $context
	 * @param PropertyProperty $node
	 */
	public function __construct( Property $property, Context $context, PropertyProperty $node ) {
		parent::__construct( $node, $context );

		$this->property = $property;
	}

	/**
	 * Returns the property name, including the `$` sigil.
	 *
	 * @return string
	 */
	public function getName() {
		return '$' . parent::getName();
	}

	/**
	 * Returns the source representation of the default value, or null if
	 * the property has no default.
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
	 * Returns the visibility of this property: 'public', 'protected', or
	 * 'private'.
	 *
	 * @return string
	 */
	public function getVisibility() {
		if ( $this->property->isProtected() ) {
			return 'protected';
		}

		if ( $this->property->isPrivate() ) {
			return 'private';
		}

		return 'public';
	}

	/**
	 * @return bool
	 */
	public function isStatic() {
		return $this->property->isStatic();
	}

	/**
	 * @return \phpDocumentor\Reflection\DocBlock|null
	 */
	public function getDocBlock() {
		return $this->extractDocBlock( $this->property );
	}
}
