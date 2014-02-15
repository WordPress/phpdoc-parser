<?php

use phpDocumentor\Reflection\FunctionReflector\ArgumentReflector;

class WP_Reflection_HookArgumentReflector extends ArgumentReflector {

	/** @var phpDocumentor\Reflection\DocBlock\Tag\ParamTag $param_tag */
	protected $param_tag;

	/**
	 * Returns the name of the argument.
	 *
	 * @return string
	 */
    public function getName() {
    	if ( $this->param_tag ) {
    		return $this->param_tag->getVariableName();
    	}

    	if ( get_class( $this->node->value ) == 'PHPParser_Node_Expr_Assign' ) {
    		return '$' . $this->node->value->var->name;
    	}

        return '$' . $this->node->value->name;
    }

    /**
     * Sets the @param tag object for the argument.
     *
     * @param phpDocumentor\Reflection\DocBlock\Tag\ParamTag $param_tag
     */
    public function setParamTag( phpDocumentor\Reflection\DocBlock\Tag\ParamTag $param_tag ) {
    	$this->param_tag = $param_tag;
    }
}
