<?php

namespace WP_Parser;

use phpDocumentor\Reflection\DocBlock;
use PhpParser\Comment\Doc;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * Reflection class for a full file.
 *
 * Parses a PHP file and collects its docblock, includes, constants,
 * functions, and classes, along with WordPress hooks and the function,
 * method, and constructor calls used in each scope.
 */
class File_Reflector implements NodeVisitor {
	/**
	 * Attribute key used to store queued uses on PHP-Parser nodes.
	 */
	const USES_ATTRIBUTE = 'wp_parser_uses';

	/**
	 * List of elements used in global scope in this file, indexed by element type.
	 *
	 * @var array {
	 *      @type Hook_Reflector[] $hooks     The action and filters.
	 *      @type Function_Call_Reflector[] $functions The functions called.
	 * }
	 */
	public $uses = array();

	/**
	 * List of elements used in the current class scope, indexed by method.
	 *
	 * @var array[][] {@see \WP_Parser\File_Reflector::$uses}
	 */
	protected $method_uses_queue = array();

	/**
	 * Stack of class/method/function nodes currently being parsed.
	 *
	 * @see \WP_Parser\File_Reflector::getLocation()
	 * @var Node[]
	 */
	protected $location = array();

	/**
	 * Last DocBlock associated with a non-documentable element.
	 *
	 * @var \PhpParser\Comment\Doc
	 */
	protected $last_doc = null;

	/**
	 * The name of the file associated with this reflection object.
	 *
	 * @var string
	 */
	protected $filename = '';

	/**
	 * The contents of this file.
	 *
	 * @var string
	 */
	protected $contents = '';

	/**
	 * Namespace context (namespace and aliases), updated during traversal
	 * and shared with every element reflector created for this file.
	 *
	 * @var File_Context
	 */
	protected $context;

	/**
	 * The file-level DocBlock, if one was found.
	 *
	 * @var DocBlock|null
	 */
	protected $doc_block;

	/**
	 * @var Include_Reflector[]
	 */
	protected $includes = array();

	/**
	 * @var Constant_Reflector[]
	 */
	protected $constants = array();

	/**
	 * @var Class_Reflector[]
	 */
	protected $classes = array();

	/**
	 * @var Function_Reflector[]
	 */
	protected $functions = array();

	/**
	 * Reads the file to reflect.
	 *
	 * @param string $file Path of the file.
	 *
	 * @throws \RuntimeException If the file does not exist or is unreadable.
	 */
	public function __construct( $file ) {
		if ( ! is_string( $file ) || ! is_readable( $file ) ) {
			throw new \RuntimeException(
				'The given file should be a string, should exist on the filesystem and should be readable'
			);
		}

		$this->filename = $file;
		$this->contents = file_get_contents( $file );
		$this->context  = new File_Context();
	}

	/**
	 * Parses the file and populates the reflected elements.
	 */
	public function process() {
		// With big fluent interfaces it can happen that PHP-Parser's Traverser
		// exceeds the 100 recursions limit; we set it to 10000 to be sure.
		ini_set( 'xdebug.max_nesting_level', 10000 );

		try {
			$nodes = ( new ParserFactory() )->createForNewestSupportedVersion()->parse( $this->contents );

			$traverser = new NodeTraverser();
			$traverser->addVisitor( new NameResolver() );
			$traverser->addVisitor( $this );
			$traverser->traverse( $nodes );
		} catch ( Error $e ) {
			echo 'Parse Error: ', $e->getMessage();
		}
	}

	/**
	 * @return string
	 */
	public function getFilename() {
		return $this->filename;
	}

	/**
	 * @param string $filename
	 */
	public function setFilename( $filename ) {
		$this->filename = $filename;
	}

	/**
	 * Returns the file-level DocBlock, if one was found.
	 *
	 * @return DocBlock|null
	 */
	public function getDocBlock() {
		return $this->doc_block;
	}

	/**
	 * @return Include_Reflector[]
	 */
	public function getIncludes() {
		return $this->includes;
	}

	/**
	 * @return Constant_Reflector[]
	 */
	public function getConstants() {
		return $this->constants;
	}

	/**
	 * @return Function_Reflector[]
	 */
	public function getFunctions() {
		return $this->functions;
	}

	/**
	 * @return Class_Reflector[]
	 */
	public function getClasses() {
		return $this->classes;
	}

	/**
	 * Detects the file-level DocBlock before traversal starts.
	 *
	 * @param Node[] $nodes
	 *
	 * @return Node[]
	 */
	public function beforeTraverse( array $nodes ) {
		$node = null;
		$key  = 0;
		foreach ( $nodes as $k => $n ) {
			if ( ! $n instanceof Node\Stmt\InlineHTML ) {
				$node = $n;
				$key  = $k;
				break;
			}
		}

		if ( $node ) {
			$comments = (array) $node->getAttribute( 'comments' );

			// Remove non-DocBlock comments.
			$comments = array_values(
				array_filter(
					$comments,
					function ( $comment ) {
						return $comment instanceof Doc;
					}
				)
			);

			if ( ! empty( $comments ) ) {
				try {
					$docblock = Abstract_Reflector::get_docblock_factory()->create(
						(string) $comments[0]
					);

					/*
					 * The first DocBlock in a file documents the file if
					 * - it precedes another DocBlock or
					 * - it contains a @package tag and doesn't precede a class
					 *   declaration or
					 * - it precedes a non-documentable element (thus no include,
					 *   require, class, function, define, const)
					 */
					if ( count( $comments ) > 1
						|| ( ! $node instanceof Node\Stmt\Class_
							&& ! $node instanceof Node\Stmt\Interface_
							&& $docblock->hasTag( 'package' ) )
						|| ! $this->isNodeDocumentable( $node )
					) {
						$this->doc_block = $docblock;

						// Remove the file-level DocBlock from the node's comments.
						array_shift( $comments );
					}
				} catch ( \Throwable $e ) {
					// Treat an unparsable docblock as no file docblock.
				}
			}

			// Always update the comments attribute so that standard comments
			// do not stop a DocBlock from being attached to an element.
			$node->setAttribute( 'comments', $comments );
			$nodes[ $key ] = $node;
		}

		return $nodes;
	}

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
	 *
	 * @param \PhpParser\Node $node
	 */
	public function enterNode( Node $node ) {
		// A docblock on an expression statement belongs to the expression within.
		if ( $node instanceof Node\Stmt\Expression && $this->isNodeDocumentable( $node->expr ) ) {
			$comments = $node->getAttribute( 'comments' );
			if ( ! empty( $comments ) && empty( $node->expr->getAttribute( 'comments' ) ) ) {
				$node->expr->setAttribute( 'comments', $comments );
			}
		}

		switch ( $node->getType() ) {
			// Add classes, functions, and methods to the current location stack.
			case 'Stmt_Class':
			case 'Stmt_Function':
			case 'Stmt_ClassMethod':
				array_push( $this->location, $node );
				break;

			// Parse out hook definitions and function calls and add them to the queue.
			case 'Expr_FuncCall':
				$function = new Function_Call_Reflector( $node, $this->context );

				// Add the call to the list of functions used in this scope.
				$this->add_use( 'functions', $function );

				if ( $this->isFilter( $node ) ) {
					if ( $this->last_doc && ! $node->getDocComment() ) {
						$node->setAttribute( 'comments', array( $this->last_doc ) );
						$this->last_doc = null;
					}

					$hook = new Hook_Reflector( $node, $this->context );

					// Add it to the list of hooks used in this scope.
					$this->add_use( 'hooks', $hook );
				}
				break;

			// Parse out method calls, so we can export where methods are used.
			case 'Expr_MethodCall':
				$method = new Method_Call_Reflector( $node, $this->context );

				// Add it to the list of methods used in this scope.
				$this->add_use( 'methods', $method );
				break;

			// Parse out method calls, so we can export where methods are used.
			case 'Expr_StaticCall':
				$method = new Static_Method_Call_Reflector( $node, $this->context );

				// Add it to the list of methods used in this scope.
				$this->add_use( 'methods', $method );
				break;

			// Parse out `new Class()` calls as uses of Class::__construct().
			case 'Expr_New':
				$method = new Method_Call_Reflector( $node, $this->context );

				// Add it to the list of methods used in this scope.
				$this->add_use( 'methods', $method );
				break;
		}

		// Pick up DocBlock from non-documentable elements so that it can be assigned
		// to the next hook if necessary. We don't do this for name nodes, since even
		// though they aren't documentable, they still carry the docblock from their
		// corresponding class/constant/function/etc. that they are the name of. If
		// we don't ignore them, we'll end up picking up docblocks that are already
		// associated with a named element, and so aren't really from a non-
		// documentable element after all.
		if (
			! $this->isNodeDocumentable( $node )
			&& 'Name' !== $node->getType()
			&& 'Name_FullyQualified' !== $node->getType()
			&& ( $docblock = $node->getDocComment() ) ) {
			$this->last_doc = $docblock;
		}
	}

	/**
	 * Collects reflected elements, assigns queued hooks to functions, and
	 * updates the node stack on leaving a node.
	 *
	 * The reflector for a node isn't created until the node is left, at
	 * which point any queued uses can be assigned to it.
	 *
	 * @param \PhpParser\Node $node
	 */
	public function leaveNode( Node $node ) {
		switch ( get_class( $node ) ) {
			case 'PhpParser\Node\Stmt\Use_':
				foreach ( $node->uses as $use ) {
					$this->context->setNamespaceAlias(
						(string) $use->getAlias(),
						$this->nameToString( $use->name )
					);
				}
				break;

			case 'PhpParser\Node\Stmt\Namespace_':
				$this->context->setNamespace(
					isset( $node->name ) && $node->name ? $this->nameToString( $node->name ) : ''
				);
				break;

			case 'PhpParser\Node\Stmt\Class_':
				$class = new Class_Reflector( $node, $this->context );
				$class->parseSubElements();
				$this->classes[] = $class;
				break;

			case 'PhpParser\Node\Stmt\Function_':
				$this->functions[] = new Function_Reflector( $node, $this->context );
				break;

			case 'PhpParser\Node\Stmt\Const_':
				foreach ( $node->consts as $constant ) {
					$this->constants[] = new Constant_Reflector( $node, $this->context, $constant );
				}
				break;

			case 'PhpParser\Node\Expr\FuncCall':
				if ( ( $node->name instanceof Node\Name )
					&& 'define' == $node->name
					&& isset( $node->args[0] )
					&& isset( $node->args[1] )
				) {
					// Transform the first argument of the define function call into a constant name.
					$name = str_replace(
						array( '\\\\', '"', "'" ),
						array( '\\', '', '' ),
						trim( $this->pretty_print_value( $node->args[0]->value ), '\'' )
					);

					$name_parts = explode( '\\', $name );
					$short_name = end( $name_parts );

					$constant                 = new Node\Const_( $short_name, $node->args[1]->value, $node->getAttributes() );
					$constant->namespacedName = new Node\Name( $name );

					$constant_statement = new Node\Stmt\Const_( array( $constant ) );
					$constant_statement->setAttribute( 'comments', array( $node->getDocComment() ) );

					$this->constants[] = new Constant_Reflector( $constant_statement, $this->context, $constant );
				}
				break;

			case 'PhpParser\Node\Expr\Include_':
				$this->includes[] = new Include_Reflector( $node, $this->context );
				break;
		}

		switch ( $node->getType() ) {
			case 'Stmt_Class':
				$class = end( $this->classes );
				if ( ! empty( $this->method_uses_queue ) ) {
					foreach ( $class->getMethods() as $method ) {
						$method_name = $method->getName();
						if ( isset( $this->method_uses_queue[ $method_name ] ) ) {
							if ( isset( $this->method_uses_queue[ $method_name ]['methods'] ) ) {
								/*
								 * For methods used in a class, set the class on the method call.
								 * That allows us to later get the correct class name for $this, self, parent.
								 */
								foreach ( $this->method_uses_queue[ $method_name ]['methods'] as $method_call ) {
									/** @var Method_Call_Reflector $method_call */
									$method_call->set_class( $class );
								}
							}

							$method->uses = $this->method_uses_queue[ $method_name ];
						}
					}
				}

				$this->method_uses_queue = array();
				array_pop( $this->location );
				break;

			case 'Stmt_Function':
				$function = array_pop( $this->location );
				$uses     = $this->get_node_uses( $function );
				if ( ! empty( $uses ) ) {
					end( $this->functions )->uses = $uses;
				}
				break;

			case 'Stmt_ClassMethod':
				$method = array_pop( $this->location );

				/*
				 * Store the list of elements used by this method in the queue. We'll
				 * assign them to the method upon leaving the class (see above).
				 */
				$uses = $this->get_node_uses( $method );
				if ( ! empty( $uses ) ) {
					$this->method_uses_queue[ (string) $method->name ] = $uses;
				}
				break;
		}
	}

	/**
	 * @param Node[] $nodes
	 */
	public function afterTraverse( array $nodes ) {
	}

	/**
	 * Checks whether the given node is a documentable element.
	 *
	 * @param \PhpParser\Node $node
	 *
	 * @return bool
	 */
	protected function isNodeDocumentable( Node $node ) {
		if ( $node instanceof Node\Stmt\Expression ) {
			$node = $node->expr;
		}

		return ( $node instanceof Node\Stmt\Class_ )
			|| ( $node instanceof Node\Stmt\Interface_ )
			|| ( $node instanceof Node\Stmt\ClassConst )
			|| ( $node instanceof Node\Stmt\ClassMethod )
			|| ( $node instanceof Node\Stmt\Const_ )
			|| ( $node instanceof Node\Stmt\Function_ )
			|| ( $node instanceof Node\Stmt\Property )
			|| ( $node instanceof Node\Stmt\PropertyProperty )
			|| ( $node instanceof Node\Stmt\Trait_ )
			|| ( $node instanceof Node\Expr\Include_ )
			|| ( $node instanceof Node\Expr\FuncCall
				&& ( $node->name instanceof Node\Name )
				&& 'define' == $node->name )
			|| ( $node instanceof Node\Expr\FuncCall
				&& $this->isFilter( $node ) );
	}

	/**
	 * @param \PhpParser\Node $node
	 *
	 * @return bool
	 */
	protected function isFilter( Node $node ) {
		if (
			'Name' !== $node->name->getType() &&
			'Name_FullyQualified' !== $node->name->getType()
		) {
			return false;
		}

		$calling = (string) $node->name;

		$functions = array(
			'apply_filters',
			'apply_filters_ref_array',
			'apply_filters_deprecated',
			'do_action',
			'do_action_ref_array',
			'do_action_deprecated',
		);

		return in_array( $calling, $functions );
	}

	/**
	 * Returns the node whose scope contains the parser's current position:
	 * the innermost function, method, or class being traversed, or this
	 * reflector itself in file scope.
	 *
	 * @return File_Reflector|Node
	 */
	protected function getLocation() {
		return empty( $this->location ) ? $this : end( $this->location );
	}

	/**
	 * Add a used element to the current parser scope.
	 *
	 * File-scope uses are stored on this reflector, while function and method
	 * scope uses are stored as PHP-Parser node attributes until matching
	 * reflectors are available.
	 *
	 * @param string $type
	 * @param object $reflector
	 */
	protected function add_use( $type, $reflector ) {
		$location = $this->getLocation();

		if ( $location instanceof self ) {
			$this->uses[ $type ][] = $reflector;
			return;
		}

		$uses            = $this->get_node_uses( $location );
		$uses[ $type ][] = $reflector;
		$location->setAttribute( self::USES_ATTRIBUTE, $uses );
	}

	/**
	 * Get queued uses from a PHP-Parser node.
	 *
	 * @param \PhpParser\Node $node
	 *
	 * @return array
	 */
	protected function get_node_uses( Node $node ) {
		$uses = $node->getAttribute( self::USES_ATTRIBUTE, array() );

		return is_array( $uses ) ? $uses : array();
	}

	/**
	 * Returns a string representation of a PHP-Parser name-like value.
	 *
	 * @param mixed $name
	 *
	 * @return string
	 */
	protected function nameToString( $name ) {
		if ( null === $name ) {
			return '';
		}

		if ( $name instanceof Node\Name ) {
			return implode( '\\', $name->getParts() );
		}

		if ( is_object( $name ) && method_exists( $name, '__toString' ) ) {
			return (string) $name;
		}

		return (string) $name;
	}

	/**
	 * Returns the source representation of a value expression.
	 *
	 * @param Node\Expr $value
	 *
	 * @return string
	 */
	protected function pretty_print_value( Node\Expr $value ) {
		return ( new Value_Printer() )->prettyPrintExpr( $value );
	}
}
