<?php

class QP_NodeVisitor extends PHPParser_NodeVisitorAbstract {
	protected $file = '';
	protected $location = array();
	protected $printer = null;
	public $functions = null;

	public function __construct($file, $repository, &$filters) {
		$this->file = $file;
		$this->printer = new PHPParser_PrettyPrinter_Default;
		$this->functions = $repository;
		$this->filters = &$filters;
	}

	public function enterNode(PHPParser_Node $node) {
		switch ($node->getType()) {
			case 'Stmt_Class':
				array_push($this->location, $node->name);
				break;
			case 'Stmt_Function':
			case 'Stmt_ClassMethod':
				array_push($this->location, $node->name);
				$func = $this->getLocation();
				$this->functions[$func]->line = $node->getLine();
				$this->functions[$func]->file = $this->file;
				if ($node->getDocComment())
					$this->functions[$func]->doc = $node->getDocComment()->getReformattedText();
				else
					$this->functions[$func]->missingDoc = true;
				break;
			case 'Expr_FuncCall':
				$caller = $this->getLocation();

				if ( $this->isFilter($node) ) {
					$filtered = $this->processFilter($node);
					if (!$filtered)
						break;

					$this->functions[$caller]->filters[] = $filtered;
				}
				else {
					$this->handleCall($node);
				}
				break;
			case 'Expr_MethodCall':
				$this->handleCall($node);
				break;
		}
	}

	protected function isFilter(PHPParser_Node $node) {
		// Ignore variable functions
		if ($node->name->getType() !== 'Name')
			return false;

		$calling = (string) $node->name;
		if ( $calling === 'apply_filters' || $calling === 'do_action' || $calling === 'do_action_ref_array' ) {
			return true;
		}
	}

	protected function handleCall(PHPParser_Node $node) {
		// Ignore variable functions
		if (!is_string($node->name) && $node->name->getType() !== 'Name')
			return false;

		$caller = $this->getLocation();
		$callee = (string) $node->name;

		if (empty($this->functions[$caller]->uses[$callee]))
			$this->functions[$caller]->uses[$callee] = array();

		$this->functions[$caller]->uses[$callee][] = $node->getLine();

		if (empty($this->functions[$callee]->used_by[$caller]))
			$this->functions[$callee]->used_by[$caller] = array();

		$this->functions[$callee]->used_by[$caller][] = $node->getLine();
	}

	protected function processFilter(PHPParser_Node $node) {
		$filter = $node->args[0]->value;
		$name = '';
		$nameParts = array();
		switch ($filter->getType()) {
			case 'Expr_Concat':
			case 'Scalar_Encapsed':
				$name = $this->printer->prettyPrintExpr($filter);
				$nameParts = $filter->parts;
				break;
			case 'Scalar_String':
				$name = "'" . $filter->value . "'";
				$nameParts[] = $name;
				break;
			case 'Expr_Variable':
				return false;
		}

		$caller = new QP_Caller;
		$caller->name = $this->getLocation();
		$caller->file = $this->file;
		$caller->line = $node->getLine();
		$caller->source = $this->printer->prettyPrintExpr($node);

		$args = $node->args;
		array_shift($args);

		switch ((string) $node->name) {
			case 'do_action':
				$caller->type = 'action';
				break;
			case 'do_action_ref_array':
				$args = $args[0]->value->items;
				$caller->type = 'action_reference';
				break;
			case 'apply_filters':
				$caller->type = 'filter';
				break;
		}
		#$caller->args = $args;

		$this->filters[$name]->callers[] = $caller;

		return $name;
	}

	protected function getLocation() {
		$caller = implode('::', $this->location);
		if (empty($caller))
			$caller = '__main';
		return $caller;
	}

	protected function parsePHPDoc($doc) {
		return Codex_Generator_Phpdoc_Parser::parse_doc($function->doc);
	}

	public function leaveNode(PHPParser_Node $node) {
		switch ($node->getType()) {
			case 'Stmt_Class':
			case 'Stmt_Function':
			case 'Stmt_ClassMethod':
				array_pop($this->location);
				break;
		}
	}
}