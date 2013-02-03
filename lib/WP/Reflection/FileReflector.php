<?php

use phpDocumentor\Reflection;
use phpDocumentor\Reflection\FileReflector;

/**
 * Reflection class for a full file.
 *
 * Extends the FileReflector from phpDocumentor to parse out WordPress
 * hooks and note function relationships.
 */
class WP_Reflection_FileReflector extends FileReflector {
	/**
	 * List of hooks defined in global scope in this file.
	 *
	 * @var WP_Reflection_HookReflector[]
	 */
	public $hooks = array();

	/**
	 * Stack of classes/methods/functions currently being parsed.
	 *
	 * @see WP_Reflection_FileReflector::getLocation()
	 * @var phpDocumentor\Reflection\BaseReflector[]
	 */
	protected $location = array();

	public function enterNode(PHPParser_Node $node) {
		parent::enterNode($node);

		switch ($node->getType()) {
			// Add classes, functions, and methods to the current location stack
			case 'Stmt_Class':
				array_push($this->location, end($this->classes));
				break;
			case 'Stmt_Function':
				array_push($this->location, end($this->functions));
				break;
			case 'Stmt_ClassMethod':
				$method = $this->findMethodReflector($this->getLocation(), $node);
				if ($method) {
					array_push($this->location, $method);
				} else {
					// Repeat the current location so that leaveNode() doesn't
					// pop it off
					array_push($this->location, $this->getLocation());
				}
				break;

			// Parse out hook definitions and add them to the current location
			case 'Expr_FuncCall':
				if ($this->isFilter($node)) {
					$hook = new WP_Reflection_HookReflector($node, $this->context);
					$this->getLocation()->hooks[] = $hook;
				}
				break;
		}
	}

	public function leaveNode(PHPParser_Node $node) {
		switch ($node->getType()) {
			case 'Stmt_Class':
			case 'Stmt_ClassMethod':
			case 'Stmt_Function':
			case 'Stmt_Interface':
				array_pop($this->location);
				break;
		}
	}

	protected function isFilter(PHPParser_Node $node) {
		// Ignore variable functions
		if ($node->name->getType() !== 'Name')
			return false;

		$calling = (string) $node->name;
		return ( $calling === 'apply_filters' || $calling === 'do_action' || $calling === 'do_action_ref_array' );
	}

	protected function getLocation() {
		return empty($this->location) ? $this : end($this->location);
	}

	/**
	 * Find the MethodReflector in a ClassReflector that matches the
	 * given Stmt_ClassMethod node.
	 *
	 * @param phpDocumentor\Reflection\ClassReflector $class Class to search in
	 * @param PHPParser_Node_Stmt_ClassMethod $node AST node to match with
	 * @return phpDocumentor\Reflection\MethodReflector|bool
	 */
	protected function findMethodReflector($class, PHPParser_Node_Stmt_ClassMethod $node) {
		if (!$class instanceof Reflection\ClassReflector)
			return false;

		$found = false;
		$method = new Reflection\ClassReflector\MethodReflector($node, $this->context);

		foreach ($class->getMethods() as $poss_method) {
			if ($method->getName() === $poss_method->getName()
				&& $method->getVisibility() === $poss_method->getVisibility()
				&& $method->isAbstract() === $poss_method->isAbstract()
				&& $method->isStatic() === $poss_method->isStatic()
				&& $method->isFinal() === $poss_method->isFinal()
			) {
				$found = $poss_method;
				break;
			}
		}

		return $found;
	}
}
