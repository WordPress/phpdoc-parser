<?php

namespace WP_Parser;

use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;

/**
 * Custom reflector for WordPress hooks.
 */
class Hook_Reflector {

	/**
	 * The function call node representing the hook.
	 *
	 * @var Node\Expr\FuncCall
	 */
	protected $node;

	/**
	 * Pretty printer for extracting hook names.
	 *
	 * @var PrettyPrinter
	 */
	protected $pretty_printer;

	/**
	 * Initialize the hook reflector.
	 *
	 * @param Node\Expr\FuncCall $node The function call node.
	 */
	public function __construct( Node\Expr\FuncCall $node ) {
		$this->node = $node;
		$this->pretty_printer = new PrettyPrinter();
	}

	/**
	 * Get the hook name.
	 *
	 * @return string The cleaned hook name.
	 */
	public function getName() {
		if ( empty( $this->node->args ) ) {
			return '';
		}

		$name_expr = $this->pretty_printer->prettyPrintExpr( $this->node->args[0]->value );
		return $this->cleanupName( $name_expr );
	}

	/**
	 * Get the hook type (action or filter).
	 *
	 * @return string 'action' or 'filter'.
	 */
	public function getType() {
		if ( ! $this->node->name instanceof Node\Name ) {
			return 'unknown';
		}

		$function_name = $this->node->name->toString();

		$filter_functions = array(
			'apply_filters',
			'apply_filters_ref_array',
			'apply_filters_deprecated',
		);

		$action_functions = array(
			'do_action',
			'do_action_ref_array',
			'do_action_deprecated',
		);

		if ( in_array( $function_name, $filter_functions ) ) {
			return 'filter';
		}

		if ( in_array( $function_name, $action_functions ) ) {
			return 'action';
		}

		return 'unknown';
	}

	/**
	 * Get the line number where the hook is defined.
	 *
	 * @return int Line number.
	 */
	public function getLine() {
		return $this->node->getStartLine();
	}

	/**
	 * Get the end line number of where the hook is defined.
	 *
	 * @return int End line number.
	 */
	public function getEndLine() {
		return $this->node->getEndLine();
	}

	/**
	 * Get the hook arguments.
	 *
	 * @return array List of hook arguments.
	 */
	public function getArguments() {
		$arguments = array();

		// Skip the first argument (hook name) and process the rest
		$args = array_slice( $this->node->args, 1 );

		foreach ( $args as $index => $arg ) {
			$arguments[] = $this->pretty_printer->prettyPrintExpr( $arg->value );
		}

		return $arguments;
	}

	/**
	 * Get the docblock associated with this hook.
	 *
	 * @return string|null DocBlock text or null if none.
	 */
	public function getDocComment() {
		$doc_comment = $this->node->getDocComment();
		return $doc_comment ? $doc_comment->getText() : null;
	}

	/**
	 * Clean up hook name by handling variables and concatenation.
	 *
	 * @param string $name Raw hook name expression.
	 * @return string Cleaned hook name.
	 */
	private function cleanupName( $name ) {
		// Remove quotes from simple strings
		if ( preg_match( '/^[\'"]([^\'"]*)[\'"]$/', $name, $matches ) ) {
			return $matches[1];
		}

		// Handle concatenated strings with variables
		// Pattern: 'string' . $variable . 'string'
		if ( preg_match(
			'/(?:[\'"]([^\'"]*)[\'"]\s*\.\s*)?' .  // First string part (optional)
			'(\$[^\s]*)' .                         // Variable part
			'(?:\s*\.\s*[\'"]([^\'"]*)[\'"])?/',   // Second string part (optional)
			$name, 
			$matches 
		) ) {
			$first_part = isset( $matches[1] ) ? $matches[1] : '';
			$variable = $matches[2];
			$last_part = isset( $matches[3] ) ? $matches[3] : '';

			return $first_part . '{' . $variable . '}' . $last_part;
		}

		// Return as-is if we can't parse it
		return $name;
	}

	/**
	 * Convert hook to array format for export.
	 *
	 * @return array Hook data array.
	 */
	public function toArray() {
		return array(
			'name' => $this->getName(),
			'type' => $this->getType(),
			'line' => $this->getLine(),
			'end_line' => $this->getEndLine(),
			'arguments' => $this->getArguments(),
			'doc_comment' => $this->getDocComment(),
		);
	}
}
