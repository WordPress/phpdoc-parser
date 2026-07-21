<?php

namespace WP_Parser;

use phpDocumentor\Reflection\DocBlock\Context;
use PhpParser\Node;

/**
 * A reflection of a function declaration.
 */
class Function_Reflector extends Abstract_Reflector {

	/**
	 * @var \PhpParser\Node\Stmt\Function_|\PhpParser\Node\Stmt\ClassMethod
	 */
	protected $node;

	/**
	 * @var Argument_Reflector[]
	 */
	protected $arguments = array();

	/**
	 * Elements used within this function, indexed by element type.
	 *
	 * Assigned by the File_Reflector once the function has been fully
	 * traversed.
	 *
	 * @var array
	 */
	public $uses = array();

	/**
	 * @param Node    $node
	 * @param Context $context
	 */
	public function __construct( Node $node, Context $context ) {
		parent::__construct( $node, $context );

		foreach ( $node->params as $param ) {
			$reflector = new Argument_Reflector( $param, $context );

			$this->arguments[ $reflector->getName() ] = $reflector;
		}
	}

	/**
	 * @return Argument_Reflector[]
	 */
	public function getArguments() {
		return $this->arguments;
	}
}
