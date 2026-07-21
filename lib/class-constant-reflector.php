<?php

namespace WP_Parser;

use PhpParser\Node\Const_;
use PhpParser\Node\Stmt\Const_ as ConstStmt;

/**
 * A reflection of a file-level constant, declared with `const` or
 * `define()`.
 */
class Constant_Reflector extends Abstract_Reflector {

	/**
	 * The constant statement holding the docblock.
	 *
	 * @var ConstStmt
	 */
	protected $constant;

	/**
	 * @var Const_
	 */
	protected $node;

	/**
	 * @param ConstStmt $stmt
	 * @param File_Context   $context
	 * @param Const_    $node
	 */
	public function __construct( ConstStmt $stmt, File_Context $context, Const_ $node ) {
		parent::__construct( $node, $context );

		$this->constant = $stmt;
	}

	/**
	 * Returns the source representation of the constant's value.
	 *
	 * @return string
	 */
	public function getValue() {
		return $this->getRepresentationOfValue( $this->node->value );
	}

	/**
	 * @return \phpDocumentor\Reflection\DocBlock|null
	 */
	public function getDocBlock() {
		return $this->extractDocBlock( $this->constant );
	}
}
