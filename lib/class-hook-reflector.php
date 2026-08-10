<?php

namespace WP_Parser;

use phpDocumentor\Reflection\BaseReflector;

/**
 * Custom reflector for WordPress hooks.
 */
class Hook_Reflector extends BaseReflector {

	/**
	 * Get the hook name as it is spelled in the source.
	 *
	 * The name is printed from the source expression instead of read from the
	 * interpreted string value. Interpreting escape sequences may produce bytes
	 * that are not valid UTF-8, which cannot be encoded as JSON.
	 *
	 * @return string
	 */
	public function getName() {
		$printer = new Pretty_Printer();
		return $this->cleanupName( $printer->prettyPrintExpr( $this->node->args[0]->value ) );
	}

	/**
	 * @param string $name
	 *
	 * @return string
	 */
	private function cleanupName( $name ) {
		$matches = array();

		// quotes on both ends of a string
		// The quoted body may contain the other quote character, as in "it's",
		// or an escaped copy of the quote that delimits it, as in 'it\'s'.
		if ( preg_match( '/^([\'"])((?:(?!\1)[^\\\\]|\\\\.)*)\1$/s', $name, $matches ) ) {
			return $matches[2];
		}

		// two concatenated things, last one of them a variable
		if ( preg_match(
			'/(?:[\'"]([^\'"]*)[\'"]\s*\.\s*)?' . // First filter name string (optional)
			'(\$[^\s]*)' .                        // Dynamic variable
			'(?:\s*\.\s*[\'"]([^\'"]*)[\'"])?/',  // Second filter name string (optional)
			$name, $matches ) ) {

			if ( isset( $matches[3] ) ) {
				return $matches[1] . '{' . $matches[2] . '}' . $matches[3];
			} else {
				return $matches[1] . '{' . $matches[2] . '}';
			}
		}

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
			case 'apply_filters_deprecated':
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
