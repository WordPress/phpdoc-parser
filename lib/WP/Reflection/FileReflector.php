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
	/** @var PHPParser_Node[] */
	protected $location = array();

	public function enterNode(PHPParser_Node $node) {
		$prettyPrinter = new PHPParser_PrettyPrinter_Zend;

		switch ($node->getType()) {
			case 'Stmt_Use':
				/** @var PHPParser_Node_Stmt_UseUse $use */
				foreach ($node->uses as $use) {
					$this->context->setNamespaceAlias(
						$use->alias,
						implode('\\', $use->name->parts)
					);
				}
				break;
			case 'Stmt_Namespace':
				$this->context->setNamespace(implode('\\', $node->name->parts));
				break;
			case 'Stmt_Class':
				$class = new Reflection\ClassReflector($node, $this->context);
				array_push($this->location, $class);
				$class->parseSubElements();
				$this->classes[] = $class;
				break;
			case 'Stmt_Trait':
				$trait = new Reflection\TraitReflector($node, $this->context);
				$this->traits[] = $trait;
				break;
			case 'Stmt_Interface':
				$interface = new Reflection\InterfaceReflector($node, $this->context);
				array_push($this->location, $interface);
				$interface->parseSubElements();
				$this->interfaces[] = $interface;
				break;
			case 'Stmt_Function':
				$function = new Reflection\FunctionReflector($node, $this->context);
				array_push($this->location, $function);
				$this->functions[] = $function;
				break;
			case 'Stmt_Const':
				foreach ($node->consts as $constant) {
					$reflector = new Reflection\ConstantReflector(
						$node,
						$this->context,
						$constant
					);
					$this->constants[] = $reflector;
				}
				break;
			case 'Expr_FuncCall':
				if ($node->name instanceof PHPParser_Node_Name && $node->name == 'define') {
					$name = trim($prettyPrinter->prettyPrintExpr($node->args[0]->value), '\'');
					$constant = new PHPParser_Node_Const($name, $node->args[1]->value, $node->getAttributes());
					$constant->namespacedName = new PHPParser_Node_Name(
						($this->current_namespace ? $this->current_namespace.'\\' : '') . $name
					);

					$constant_statement = new PHPParser_Node_Stmt_Const(array($constant));
					$constant_statement->setAttribute('comments', array($node->getDocComment()));
					$this->constants[] = new Reflection\ConstantReflector($constant_statement, $this->context, $constant);
				} else if ($this->isFilter($node)) {
					$hook = new WP_Reflection_HookReflector($node, $this->context);
					$this->getLocation()->hooks[] = $hook;
				}
				break;
			case 'Expr_Include':
				$include = new Reflection\IncludeReflector($node, $this->context);
				$this->includes[] = $include;
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
		if (empty($this->location)) {
			return $this;
		} else {
			return end($this->location);
		}
	}
}
