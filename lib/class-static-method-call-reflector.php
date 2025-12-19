<?php

namespace WP_Parser;

use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;

/**
 * A reflection of a method call expression.
 */
class Static_Method_Call_Reflector {

	/**
	 * The static method call node.
	 *
	 * @var Node\Expr\StaticCall
	 */
	protected $node;

	/**
	 * Pretty printer for extracting names.
	 *
	 * @var PrettyPrinter
	 */
	protected $pretty_printer;

	/**
	 * The class context if available.
	 *
	 * @var object|null
	 */
	protected $class_context;

	/**
	 * Initialize the static method call reflector.
	 *
	 * @param Node\Expr\StaticCall $node The static method call node.
	 */
	public function __construct( Node\Expr\StaticCall $node ) {
		$this->node = $node;
		$this->pretty_printer = new PrettyPrinter();
	}

	/**
	 * Get the method name.
	 *
	 * @return string Method name.
	 */
	public function getName() {
		if ( $this->node->name instanceof Node\Identifier ) {
			return $this->node->name->toString();
		}

		// Handle variable method calls like Class::$method()
		return $this->pretty_printer->prettyPrintExpr( $this->node->name );
	}

	/**
	 * Get the class name being called.
	 *
	 * @return string Class name.
	 */
	public function getClass() {
		if ( $this->node->class instanceof Node\Name ) {
			$class_name = $this->node->class->toString();
			
			// Resolve self and parent to actual class names
			if ( 'self' === $class_name && $this->class_context ) {
				$class_name = $this->class_context->name->toString();
			} elseif ( 'parent' === $class_name && $this->class_context && $this->class_context->extends ) {
				$class_name = $this->class_context->extends->toString();
			}
			
			// Add leading backslash for fully qualified class names
			return $class_name && ! str_starts_with( $class_name, '\\' ) ? '\\' . $class_name : $class_name;
		}

		// Handle variable class calls like $class::method()
		return $this->pretty_printer->prettyPrintExpr( $this->node->class );
	}

	/**
	 * Get the full method signature (Class::method).
	 *
	 * @return string Full method signature.
	 */
	public function getFullName() {
		return $this->getClass() . '::' . $this->getName();
	}

	/**
	 * Get the line number where the static method call occurs.
	 *
	 * @return int Line number.
	 */
	public function getLine() {
		return $this->node->getStartLine();
	}

	/**
	 * Get the end line number of where the method call occurs.
	 *
	 * @return int End line number.
	 */
	public function getEndLine() {
		return $this->node->getEndLine();
	}

	/**
	 * Get the method call arguments.
	 *
	 * @return array List of arguments.
	 */
	public function getArguments() {
		$arguments = array();

		foreach ( $this->node->args as $arg ) {
			$arguments[] = $this->pretty_printer->prettyPrintExpr( $arg->value );
		}

		return $arguments;
	}

	/**
	 * Check if this is a static method call.
	 *
	 * @return bool Always true for static method calls.
	 */
	public function isStatic() {
		return true;
	}

	/**
	 * Set the class context for resolving self/parent references.
	 *
	 * @param object $class_context The class context.
	 */
	public function set_class( $class_context ) {
		$this->class_context = $class_context;
	}

	/**
	 * Check if the class is fully qualified.
	 *
	 * @return bool True if fully qualified.
	 */
	public function isFullyQualified() {
		return $this->node->class instanceof Node\Name && $this->node->class->isFullyQualified();
	}

	/**
	 * Get the namespace of the class.
	 *
	 * @return string|null Namespace or null if not namespaced.
	 */
	public function getNamespace() {
		if ( ! $this->node->class instanceof Node\Name ) {
			return null;
		}

		$parts = $this->node->class->parts;
		if ( count( $parts ) <= 1 ) {
			return null;
		}

		return implode( '\\', array_slice( $parts, 0, -1 ) );
	}

	/**
	 * Convert static method call to array format for export.
	 *
	 * @return array Static method call data.
	 */
	public function toArray() {
		return array(
			'name' => $this->getName(),
			'class' => $this->getClass(),
			'full_name' => $this->getFullName(),
			'line' => $this->getLine(),
			'arguments' => $this->getArguments(),
			'static' => $this->isStatic(),
			'namespace' => $this->getNamespace(),
			'fully_qualified' => $this->isFullyQualified(),
		);
	}
}
