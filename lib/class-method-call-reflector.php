<?php

namespace WP_Parser;

use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;

/**
 * A reflection of a method call expression.
 */
class Method_Call_Reflector {

	/**
	 * The method call node.
	 *
	 * @var Node\Expr\MethodCall|Node\Expr\New_
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
	 * Initialize the method call reflector.
	 *
	 * @param Node\Expr\MethodCall|Node\Expr\New_ $node The method call or new node.
	 */
	public function __construct( $node ) {
		$this->node = $node;
		$this->pretty_printer = new PrettyPrinter();
	}

	/**
	 * Get the method name.
	 *
	 * @return string Method name.
	 */
	public function getName() {
		// Handle constructor calls (new Class())
		if ( $this->node instanceof Node\Expr\New_ ) {
			return '__construct';
		}

		// Handle regular method calls
		if ( $this->node instanceof Node\Expr\MethodCall ) {
			if ( $this->node->name instanceof Node\Identifier ) {
				return $this->node->name->toString();
			}

			// Handle variable method calls like $obj->$method()
			return $this->pretty_printer->prettyPrintExpr( $this->node->name );
		}

		return '';
	}

	/**
	 * Get the class name being called.
	 *
	 * @return string Class name.
	 */
	public function getClass() {
		// Handle constructor calls (new Class())
		if ( $this->node instanceof Node\Expr\New_ ) {
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

			// Handle variable class instantiation like new $class()
			if ( $this->node->class instanceof Node\Expr ) {
				return $this->pretty_printer->prettyPrintExpr( $this->node->class );
			}
			
			// For anonymous classes or unsupported cases, return null
			return null;
		}

		// Handle regular method calls
		if ( $this->node instanceof Node\Expr\MethodCall ) {
			// Try to determine class from variable
			if ( $this->node->var instanceof Node\Expr\Variable ) {
				$var_name = $this->node->var->name;

				// Handle common patterns
				if ( '$this' === '$' . $var_name && $this->class_context ) {
					$class_name = $this->class_context->name;
					// Add leading backslash for fully qualified class names
					return $class_name && ! str_starts_with( $class_name, '\\' ) ? '\\' . $class_name : $class_name;
				}

				return '$' . $var_name;
			}

			// Handle chained calls like $obj->method()->anotherMethod()
			return $this->pretty_printer->prettyPrintExpr( $this->node->var );
		}

		return '';
	}

	/**
	 * Get the line number where the method call occurs.
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

		$args = array();
		if ( $this->node instanceof Node\Expr\MethodCall ) {
			$args = $this->node->args;
		} elseif ( $this->node instanceof Node\Expr\New_ ) {
			$args = $this->node->args ?? array();
		}

		foreach ( $args as $arg ) {
			$arguments[] = $this->pretty_printer->prettyPrintExpr( $arg->value );
		}

		return $arguments;
	}

	/**
	 * Returns whether or not this method call is a static call
	 *
	 * @return bool Whether or not this method call is a static call
	 */
	public function isStatic() {
		// Constructor calls and method calls are not static
		return false;
	}

	/**
	 * Set the class context for resolving $this references.
	 *
	 * @param object $class_context The class context.
	 */
	public function set_class( $class_context ) {
		$this->class_context = $class_context;
	}

	/**
	 * Convert method call to array format for export.
	 *
	 * @return array Method call data.
	 */
	public function toArray() {
		return array(
			'name' => $this->getName(),
			'class' => $this->getClass(),
			'line' => $this->getLine(),
			'arguments' => $this->getArguments(),
			'static' => $this->isStatic(),
		);
	}
}
