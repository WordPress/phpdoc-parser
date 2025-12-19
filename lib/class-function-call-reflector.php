<?php

namespace WP_Parser;

use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;

/**
 * A reflection of a function call expression.
 */
class Function_Call_Reflector {

	/**
	 * The function call node.
	 *
	 * @var Node\Expr\FuncCall
	 */
	protected $node;

	/**
	 * Pretty printer for extracting names.
	 *
	 * @var PrettyPrinter
	 */
	protected $pretty_printer;

	/**
	 * Initialize the function call reflector.
	 *
	 * @param Node\Expr\FuncCall $node The function call node.
	 */
	public function __construct( Node\Expr\FuncCall $node ) {
		$this->node = $node;
		$this->pretty_printer = new PrettyPrinter();
	}

	/**
	 * Get the function name.
	 *
	 * @return string Function name.
	 */
	public function getName() {
		// Handle direct name calls
		if ( $this->node->name instanceof Node\Name ) {
			return $this->node->name->toString();
		}

		// Handle variable function calls like $func()
		if ( $this->node->name instanceof Node\Expr\Variable ) {
			return '$' . $this->node->name->name;
		}

		// Handle array access like $callbacks['func']()
		if ( $this->node->name instanceof Node\Expr\ArrayDimFetch ) {
			$var_name = '';
			if ( $this->node->name->var instanceof Node\Expr\Variable ) {
				$var_name = '$' . $this->node->name->var->name;
			}

			$dim_name = '';
			if ( $this->node->name->dim ) {
				$dim_name = $this->pretty_printer->prettyPrintExpr( $this->node->name->dim );
			}

			return $var_name . '[' . $dim_name . ']';
		}

		// Handle property access like $obj->method()
		if ( $this->node->name instanceof Node\Expr\PropertyFetch ) {
			return $this->pretty_printer->prettyPrintExpr( $this->node->name );
		}

		// Fallback to pretty printing the expression
		return $this->pretty_printer->prettyPrintExpr( $this->node->name );
	}

	/**
	 * Get the short name (without namespace).
	 *
	 * @return string Short function name.
	 */
	public function getShortName() {
		if ( $this->node->name instanceof Node\Name ) {
			return $this->node->name->getLast();
		}

		return $this->getName();
	}

	/**
	 * Get the line number where the function call occurs.
	 *
	 * @return int Line number.
	 */
	public function getLine() {
		return $this->node->getStartLine();
	}

	/**
	 * Get the end line number of where the function call occurs.
	 *
	 * @return int End line number.
	 */
	public function getEndLine() {
		return $this->node->getEndLine();
	}

	/**
	 * Get the function call arguments.
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
	 * Check if this is a namespaced function call.
	 *
	 * @return bool True if namespaced.
	 */
	public function isNamespaced() {
		return $this->node->name instanceof Node\Name && $this->node->name->isFullyQualified();
	}

	/**
	 * Get the namespace of the function call.
	 *
	 * @return string|null Namespace or null if not namespaced.
	 */
	public function getNamespace() {
		if ( ! $this->node->name instanceof Node\Name ) {
			return null;
		}

		$parts = $this->node->name->parts;
		if ( count( $parts ) <= 1 ) {
			return null;
		}

		return implode( '\\', array_slice( $parts, 0, -1 ) );
	}

	/**
	 * Convert function call to array format for export.
	 *
	 * @return array Function call data.
	 */
	public function toArray() {
		return array(
			'name' => $this->getName(),
			'short_name' => $this->getShortName(),
			'line' => $this->getLine(),
			'end_line' => $this->getEndLine(),
			'arguments' => $this->getArguments(),
			'namespace' => $this->getNamespace(),
			'namespaced' => $this->isNamespaced(),
		);
	}
}
