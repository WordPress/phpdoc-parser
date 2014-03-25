<?php

namespace WP_Parser;

use phpDocumentor\Reflection;
use phpDocumentor\Reflection\FileReflector;

/**
 * Reflection class for a full file.
 *
 * Extends the FileReflector from phpDocumentor to parse out WordPress
 * hooks and note function relationships.
 */
class File_Reflector extends FileReflector {
	/**
	 * List of elements used in global scope in this file, indexed by element type.
	 *
	 * @var array {
	 *      @type \WP_Parser\Hook_Reflector[]          $hooks     The action and filters.
	 *      @type \WP_Parser\Function_Call_Reflector[] $functions The functions called.
	 * }
	 */
	public $uses = array();

	/**
	 * List of elements used in the current node scope, indexed by element type.
	 *
	 * @var array {@see \WP_Parser\File_Reflector::$uses}
	 */
	protected $uses_queue = array();

	/**
	 * List of elements used in the current class scope, indexed by method.
	 *
	 * @var array[][] {@see \WP_Parser\File_Reflector::$uses}
	 */
	protected $method_uses_queue = array();

	/**
	 * Stack of classes/methods/functions currently being parsed.
	 *
	 * @see \WP_Parser\FileReflector::getLocation()
	 * @var \phpDocumentor\Reflection\BaseReflector[]
	 */
	protected $location = array();

	/**
	 * Last DocBlock associated with a non-documentable element.
	 *
	 * @var \PHPParser_Comment_Doc
	 */
	protected $last_doc = null;

	/**
	 * Add hooks to the queue and update the node stack when we enter a node.
	 *
	 * If we are entering a class, function or method, we push it to the location
	 * stack. This is just so that we know whether we are in the file scope or not,
	 * so that hooks in the main file scope can be added to the file.
	 *
	 * We also check function calls to see if there are any actions or hooks. If
	 * there are, they are added to the file's hooks if in the global scope, or if
	 * we are in a function/method, they are added to the queue. They will be
	 * assigned to the function by leaveNode(). We also check for any other function
	 * calls and treat them similarly, so that we can export a list of functions
	 * used by each element.
	 *
	 * Finally, we pick up any docblocks for nodes that usually aren't documentable,
	 * so they can be assigned to the hooks to which they may belong.
	 */
	public function enterNode( \PHPParser_Node $node ) {
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
				$function = new \WP_Parser\Function_Call_Reflector( $node, $this->context );

				/*
				 * If the function call is in the global scope, add it to the
				 * file's function calls. Otherwise, add it to the queue so it
				 * can be added to the correct node when we leave it.
				 */
				if ( $this === $this->getLocation() ) {
					$this->uses['functions'][] = $function;
				} else {
					$this->uses_queue['functions'][] = $function;
				}

				if ( $this->isFilter( $node ) ) {
					if ( $this->last_doc && ! $node->getDocComment() ) {
						$node->setAttribute( 'comments', array( $this->last_doc ) );
						$this->last_doc = null;
					}

					$hook = new \WP_Parser\Hook_Reflector( $node, $this->context );

					/*
					 * If the hook is in the global scope, add it to the file's
					 * hooks. Otherwise, add it to the queue so it can be added to
					 * the correct node when we leave it.
					 */
					if ( $this === $this->getLocation() ) {
						$this->uses['hooks'][] = $hook;
					} else {
						$this->uses_queue['hooks'][] = $hook;
					}
				}
				break;
		}

		// Pick up DocBlock from non-documentable elements so that it can be assigned
		// to the next hook if necessary.
		if ( ! $this->isNodeDocumentable( $node ) && ( $docblock = $node->getDocComment() ) ) {
			$this->last_doc = $docblock;
		}
	}

	/**
	 * Assign queued hooks to functions and update the node stack on leaving a node.
	 *
	 * We can now access the function/method reflectors, so we can assign any queued
	 * hooks to them. The reflector for a node isn't created until the node is left.
	 */
	public function leaveNode( \PHPParser_Node $node ) {

		parent::leaveNode( $node );

		switch ( $node->getType() ) {
			case 'Stmt_Class':
				$class = end( $this->classes );
				if ( ! empty( $this->method_uses_queue ) ) {
					foreach ( $class->getMethods() as $method ) {
						if ( isset( $this->method_uses_queue[ $method->getName() ] ) ) {
							$method->uses = $this->method_uses_queue[ $method->getName() ];
						}
					}
				}

				$this->method_uses_queue = array();
				array_pop( $this->location );
				break;

			case 'Stmt_Function':
				end( $this->functions )->uses = $this->uses_queue;
				$this->uses_queue = array();
				array_pop( $this->location );
				break;

			case 'Stmt_ClassMethod':
				/*
				 * Store the list of elements used by this method in the queue. We'll
				 * assign them to the method upon leaving the class (see above).
				 */
				if ( ! empty( $this->uses_queue ) ) {
					$this->method_uses_queue[ $node->name ] = $this->uses_queue;
					$this->uses_queue = array();
				}

				array_pop( $this->location );
				break;
		}
	}

	protected function isFilter( \PHPParser_Node $node ) {
		// Ignore variable functions
		if ( $node->name->getType() !== 'Name' ) {
			return false;
		}

		$calling = (string) $node->name;

		$functions = array(
			'apply_filters',
			'apply_filters_ref_array',
			'do_action',
			'do_action_ref_array',
		);

		return in_array( $calling, $functions );
	}

	protected function getLocation() {
		return empty( $this->location ) ? $this : end( $this->location );
	}

	protected function isNodeDocumentable( \PHPParser_Node $node ) {
		return parent::isNodeDocumentable( $node )
		|| ( $node instanceof \PHPParser_Node_Expr_FuncCall
			&& $this->isFilter( $node ) );
	}
}
