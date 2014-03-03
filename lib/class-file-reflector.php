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
	 * List of hooks defined in global scope in this file.
	 *
	 * @var \WP_Parser\Hook_Reflector[]
	 */
	public $hooks = array();

	/**
	 * List of hooks defined in the current node scope.
	 *
	 * @var \WP_Parser\Hook_Reflector[]
	 */
	protected $hooks_queue = array();

	/**
	 * List of hooks defined in the current class scope, indexed by method.
	 *
	 * @var \WP_Parser\Hook_Reflector[]
	 */
	protected $method_hooks_queue = array();

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
	 * assinged to the function by leaveNode().
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

			// Parse out hook definitions and add them to the queue.
			case 'Expr_FuncCall':
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
						$this->hooks[] = $hook;
					} else {
						$this->hooks_queue[] = $hook;
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
				if ( ! empty( $this->method_hooks_queue ) ) {
					foreach ( $class->getMethods() as $method ) {
						if ( isset( $this->method_hooks_queue[ $method->getName() ] ) ) {
							$method->hooks = $this->method_hooks_queue[ $method->getName() ];
						}
					}
				}

				$this->method_hooks_queue = array();
				array_pop( $this->location );
				break;

			case 'Stmt_Function':
				end( $this->functions )->hooks = $this->hooks_queue;
				$this->hooks_queue = array();
				array_pop( $this->location );
				break;

			case 'Stmt_ClassMethod':
				if ( ! empty( $this->hooks_queue ) ) {
					$this->method_hooks_queue[ $node->name ] = $this->hooks_queue;
					$this->hooks_queue = array();
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

		return ( $calling === 'apply_filters' || $calling === 'do_action' || $calling === 'do_action_ref_array' );
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
