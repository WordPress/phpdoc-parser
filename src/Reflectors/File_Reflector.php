<?php namespace WP_Parser\Reflectors;

use phpDocumentor\Reflection\BaseReflector;
use phpDocumentor\Reflection\FileReflector;
use PhpParser\Node;
use PHPParser_Node_Expr_FuncCall;

/**
 * Class File_Reflector
 * @package WP_Parser
 */
class File_Reflector extends FileReflector {
	/**
	 * List of elements used in global scope in this file, indexed by element type.
	 *
	 * @var array {
	 *      @type Hook_Reflector[] $hooks     The action and filters.
	 *      @type Function_Reflector[] $functions The functions called.
	 * }
	 */
	public $uses = [];

	/**
	 * List of elements used in the current class scope, indexed by method.
	 *
	 * @var array[][] {@see \WP_Parser\File_Reflector::$uses}
	 */
	protected $method_uses_queue = [];

	/**
	 * Stack of classes/methods/functions currently being parsed.
	 *
	 * @see \WP_Parser\FileReflector::getLocation()
	 * @var BaseReflector[]
	 */
	protected $location = [];

	/**
	 * Last DocBlock associated with a non-documentable element.
	 *
	 * @var Comment_Doc
	 */
	protected $last_doc = null;

	public function enterNode( Node $node ) {
		parent::enterNode( $node );

		switch ( $node->getType() ) {
			// Add classes, functions, and methods to the current location stack
			case 'Stmt_Class':
			case 'Stmt_Function':
			case 'Stmt_ClassMethod':
				array_push( $this->location, $node );
				break;

			// Parse out hook definitions and function calls and add them to the queue.
			case 'Expr_FuncCall':
				$this->add_to_uses( 'functions', new Function_Reflector( $node, $this->context ) );

				if ( $this->isFilter( $node ) ) {
					if ( $this->last_doc && ! $node->getDocComment() ) {
						$node->setAttribute( 'comments', array( $this->last_doc ) );
						$this->last_doc = null;
					}

					$this->add_to_uses( 'hooks', new Hook_Reflector( $node, $this->context ) );
				}
				break;

			// Parse out method calls, so we can export where methods are used.
			case 'Expr_MethodCall':
			case 'Expr_New':
				$this->add_to_uses( 'methods', new Method_Reflector( $node, $this->context ) );
				break;

			// Parse out method calls, so we can export where methods are used.
			case 'Expr_StaticCall':
				$this->add_to_uses( 'methods', new Static_Method_Reflector( $node, $this->context ) );
				break;
		}

		/**
		 * Pick up DocBlock from non-documentable elements so that it can be assigned to the next hook if necessary.
		 * We don't do this for name nodes, since even though they aren't documentable, they still carry the docblock
		 * from their corresponding class/constant/function/etc. that they are the name of.
		 */
		$docblock = $node->getDocComment();

		if ( ! $this->isNodeDocumentable( $node ) && $node->getType() !== 'Name' && $docblock ) {
			$this->last_doc = $docblock;
		}
	}

	protected function add_to_uses( $key, $value ) {
		$this->getLocation()->uses[ $key ][] = $value;
	}

	/**
	 * Assign queued hooks to functions and update the node stack on leaving a node.
	 *
	 * We can now access the function/method reflectors, so we can assign any queued
	 * hooks to them. The reflector for a node isn't created until the node is left.
	 *
	 * @param Node $node
	 */
	public function leaveNode( Node $node ) {
		parent::leaveNode( $node );

		switch ( $node->getType() ) {
			case 'Stmt_Class':
				$class = end( $this->classes );

				if ( ! empty( $this->method_uses_queue ) ) {
					/** @var Reflection\ClassReflector\MethodReflector $method */
					foreach ( $class->getMethods() as $method ) {
						if ( ! isset( $this->method_uses_queue[ $method->getName() ] ) ) {
							continue;
						}

						if ( isset( $this->method_uses_queue[ $method->getName() ]['methods'] ) ) {
							/*
							 * For methods used in a class, set the class on the method call.
							 * That allows us to later get the correct class name for $this, self, parent.
							 */
							foreach ( $this->method_uses_queue[ $method->getName() ]['methods'] as $method_call ) {
								/** @var Method_Reflector $method_call */
								$method_call->set_class( $class );
							}
						}

						$method->uses = $this->method_uses_queue[ $method->getName() ];
					}
				}

				$this->method_uses_queue = [];
				array_pop( $this->location );
				break;

			case 'Stmt_Function':
				$item = array_pop( $this->location );
				$uses = '';

				if ( property_exists( $item, 'uses' ) ) {
					$uses = $item->uses;
				}

				end( $this->functions )->uses = $uses;
				break;

			case 'Stmt_ClassMethod':
				$method = array_pop( $this->location );

				/*
				 * Store the list of elements used by this method in the queue.
				 * We'll assign them to the method upon leaving the class (see above).
				 */
				if ( ! empty( $method->uses ) ) {
					$this->method_uses_queue[ $method->name ] = $method->uses;
				}
				break;
		}
	}

	/**
	 * @param Node $node
	 *
	 * @return bool
	 */
	protected function isFilter( Node $node ) {
		// Ignore variable functions
		if ( $node->name->getType() !== 'Name' ) {
			return false;
		}

		$calling = (string) $node->name;

		$functions = [
			'apply_filters',
			'apply_filters_ref_array',
			'apply_filters_deprecated',
			'do_action',
			'do_action_ref_array',
			'do_action_deprecated',
		];

		return in_array( $calling, $functions, true );
	}

	/**
	 * @return File_Reflector
	 */
	protected function getLocation() {
		return empty( $this->location ) ? $this : end( $this->location );
	}

	/**
	 * @param Node $node
	 *
	 * @return bool
	 */
	protected function isNodeDocumentable( Node $node ) {
		return parent::isNodeDocumentable( $node )
			   || ( $node instanceof PHPParser_Node_Expr_FuncCall && $this->isFilter( $node ) );
	}
}
