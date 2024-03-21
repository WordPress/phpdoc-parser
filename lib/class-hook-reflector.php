<?php

namespace WP_Parser;

use phpDocumentor\Reflection\BaseReflector;
use PhpParser\Node;
use PHPParser_PrettyPrinter_Default;

/**
 * Custom reflector for WordPress hooks.
 */
class Hook_Reflector extends BaseReflector {

	/**
	 * Hook names are the first argument to actions and filters.
	 *
	 * These are expected to be string values or concatenations of strings and variables.
	 *
	 * Example:
	 *
	 *     from: apply_filters( 'option_' . $option_name, $option_value );
	 *     name: option_{$option_name}
	 *
	 *     from: do_action( 'wp_insert_post', $post_ID, $post, true );
	 *     name: wp_insert_post
	 *
	 *     from: do_action( "{$old_status}_to_{$new_status}", $post );
	 *     name: {$old_status}_to_{$new_status}
	 *
	 *     from: do_action( $filter_name, $args );
	 *     name: {$filter_name}
	 *
	 * @param ?Node $node Which node to examine; defaults to the parser's current node.
	 * @return string Represents the hook's name, including any interpolations into the hook name.
	 */
	public function getName( $node = null ) {
		if ( null === $node ) {
			$node = $this->node->args[0]->value;
		}
		$printer = new PHPParser_PrettyPrinter_Default;
		$name    = $printer->prettyPrintExpr( $node );

		if ( $node instanceof \PhpParser\Node\Scalar\String_ ) {
			// "'action'" -> "action"
			return $node->value;
		} elseif ( $node instanceof \PhpParser\Node\Scalar\Encapsed ) {
			// '"action_{$var}"' -> 'action_{$var}'
			$name = '';

			foreach ( $node->parts as $part ) {
				if ( is_string( $part ) ) {
					$name .= $part;
				} else {
					$name .= $this->getName( $part );
				}
			}

			return $name;
		} elseif ( $node instanceof \PhpParser\Node\Expr\BinaryOp\Concat ) {
			// '"action_" . $var' -> 'action_{$var}'
			return $this->getName( $node->left ) . $this->getName( $node->right );
		} elseif ( $node instanceof \PhpParser\Node\Expr\PropertyFetch ) {
			// '$this->action' -> '{$this->action}'
			return "{{$name}}";
		} elseif ( $node instanceof \PhpParser\Node\Expr\Variable ) {
			// '$action' -> '{$action}'
			return "{\${$node->name}}";
		}

		/*
		 * If none of these known constructions match, then
		 * fallback to the pretty-printed version of the node.
		 *
		 * For improving the quality of the hook-name generation,
		 * replace this return statement by throwing an exception
		 * to determine which cases aren't handled, and then add
		 * them above.
		 */
		return $name;
	}

	/**
	 * @return string
	 */
	public function getShortName() {
		return $this->getName();
	}

	/**
	 * @return string
	 */
	public function getType() {
		$type = 'filter';
		switch ( (string) $this->node->name ) {
			case 'do_action':
				$type = 'action';
				break;
			case 'do_action_ref_array':
				$type = 'action_reference';
				break;
			case 'do_action_deprecated':
				$type = 'action_deprecated';
				break;
			case 'apply_filters_ref_array':
				$type = 'filter_reference';
				break;
			case 'apply_filters_deprecated';
				$type = 'filter_deprecated';
				break;
		}

		return $type;
	}

	/**
	 * @return array
	 */
	public function getArgs() {
		$printer = new Pretty_Printer;
		$args    = array();
		foreach ( $this->node->args as $arg ) {
			$args[] = $printer->prettyPrintArg( $arg );
		}

		// Skip the filter name
		array_shift( $args );

		return $args;
	}
}
