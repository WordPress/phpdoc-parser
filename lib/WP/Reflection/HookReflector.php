<?php

use phpDocumentor\Reflection\BaseReflector;

class WP_Reflection_HookReflector extends BaseReflector {
	public function getName() {
		$name = '';
		$filter = $this->node->args[0]->value;
		switch ($filter->getType()) {
			case 'Expr_Concat':
			case 'Scalar_Encapsed':
				$printer = new PHPParser_PrettyPrinter_Default;
				$name = $printer->prettyPrintExpr($filter);
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
		switch ((string) $this->node->name) {
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
		$args = array();

		if ( ! is_array( $this->node->args ) ) {
			return $args;
		}

		$params = array();
		$docblock = $this->getDocBlock();

		if ( $docblock instanceof phpDocumentor\Reflection\DocBlock ) {
			$params = $docblock->getTagsByName( 'param' );
		}

		$_args = $this->node->args;

		// Skip the filter name.
		array_shift( $_args );

		$type = $this->getType();
		if (
			isset( $_args[0] )
			&& ( 'action_reference' == $type || 'filter_reference' == $type )
			&& $_args[0]->value instanceof PHPParser_Node_Expr_Array
		) {
			$_args = $_args[0]->value->items;
		}

		foreach ( $_args as $index => $arg ) {
			$reflector = new WP_Reflection_HookArgumentReflector( $arg, $this->context );

			if ( isset( $params[ $index ] ) ) {
				$reflector->setParamTag( $params[ $index ] );
			}

			$args[] = $reflector;
		}

		return $args;
	}
}
