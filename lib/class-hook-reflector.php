<?php

namespace WP_Parser;

use phpDocumentor\Reflection\BaseReflector;
use PHPParser_PrettyPrinter_Default;

class Hook_Reflector extends BaseReflector {

	public function getName() {
		$name   = '';
		$filter = $this->node->args[0]->value;

		switch ( $filter->getType() ) {
			case 'Expr_Concat':
			case 'Scalar_Encapsed':
				$printer = new PHPParser_PrettyPrinter_Default;
				$name    = $printer->prettyPrintExpr( $filter );
				break;
			case 'Scalar_String':
				$name = "'" . $filter->value . "'";
				break;
		}

		return $name ? $name : false;
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
