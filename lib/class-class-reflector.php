<?php

namespace WP_Parser;

/**
 * A reflection of a class declaration.
 */
class Class_Reflector extends Abstract_Reflector {

	/**
	 * @var \PhpParser\Node\Stmt\Class_
	 */
	protected $node;

	/**
	 * @var Property_Reflector[]
	 */
	protected $properties = array();

	/**
	 * Methods of this class, indexed by lowercased method name.
	 *
	 * @var Method_Reflector[]
	 */
	protected $methods = array();

	/**
	 * Creates reflectors for the class's properties and methods.
	 */
	public function parseSubElements() {
		foreach ( $this->node->stmts as $stmt ) {
			if ( $stmt instanceof \PhpParser\Node\Stmt\Property ) {
				foreach ( $stmt->props as $property ) {
					$this->properties[] = new Property_Reflector( $stmt, $this->context, $property );
				}
			} elseif ( $stmt instanceof \PhpParser\Node\Stmt\ClassMethod ) {
				$this->methods[ strtolower( (string) $stmt->name ) ] = new Method_Reflector( $stmt, $this->context );
			}
		}
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
	public function isFinal() {
		return $this->node->isFinal();
	}

	/**
	 * Returns the fully qualified name of the parent class, or an empty
	 * string when the class has no parent.
	 *
	 * @return string
	 */
	public function getParentClass() {
		return isset( $this->node->extends ) ? '\\' . (string) $this->node->extends : '';
	}

	/**
	 * Returns the fully qualified names of the interfaces this class
	 * implements.
	 *
	 * @return string[]
	 */
	public function getInterfaces() {
		$names = array();

		if ( isset( $this->node->implements ) ) {
			foreach ( $this->node->implements as $interface ) {
				$names[] = '\\' . (string) $interface;
			}
		}

		return $names;
	}

	/**
	 * @return Property_Reflector[]
	 */
	public function getProperties() {
		return $this->properties;
	}

	/**
	 * @return Method_Reflector[]
	 */
	public function getMethods() {
		return $this->methods;
	}
}
