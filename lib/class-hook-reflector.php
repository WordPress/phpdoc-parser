<?php

namespace WP_Parser;

use phpDocumentor\Reflection\BaseReflector;
use PHPParser_PrettyPrinter_Default;

class Hook_Reflector extends BaseReflector {

	public function getName() {
		$printer = new PHPParser_PrettyPrinter_Default;
		return $this->cleanupName( $printer->prettyPrintExpr( $this->node->args[0]->value ) );
	}

	private function cleanupName( $name ) {
		$m = array();

		// quotes on both ends of a string
		if ( preg_match( '/^[\'"]([^\'"]*)[\'"]$/', $name, $m ) ) {
			return $m[1];
		}

		// two concatenated things, last one of them a variable
		if ( preg_match(
			'/(?:[\'"]([^\'"]*)[\'"]\s*\.\s*)?' . // First filter name string (optional)
			'(\$[^\s]*)' .                        // Dynamic variable
			'(?:\s*\.\s*[\'"]([^\'"]*)[\'"])?/',  // Second filter name string (optional)
			$name, $m ) ) {

			if ( isset( $m[3] ) ) {
				return $m[1] . '{' . $m[2] . '}' . $m[3];
			} else {
				return $m[1] . '{' . $m[2] . '}';
			}
		}

		return $name;
	}

	public function getShortName() {
		return $this->getName();
	}

	public function getType() {
		$type = 'filter';
		switch ( (string) $this->node->name ) {
			case 'do_action':
				$type = 'action';
				break;
			case 'do_action_ref_array':
				$type = 'action_reference';
				break;
			case 'apply_filters_ref_array':
				$type = 'filter_reference';
				break;
		}

		return $type;
	}

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
