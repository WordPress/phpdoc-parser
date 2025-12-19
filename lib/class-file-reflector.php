<?php

namespace WP_Parser;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

/**
 * Modern file parser using PHPParser v5 and phpstan/phpdoc-parser.
 *
 * Parses WordPress files to extract functions, classes, methods, hooks,
 * and their relationships for the developer.wordpress.org reference.
 */
class File_Reflector extends NodeVisitorAbstract {

	/**
	 * List of elements used in global scope in this file, indexed by element type.
	 *
	 * @var array{
	 *     hooks: Hook_Reflector[],
	 *     functions: Function_Call_Reflector[]
	 * }
	 */
	public $uses = array();

	/**
	 * List of elements used in the current class scope, indexed by method.
	 *
	 * @var array<string, array>
	 */
	protected $method_uses_queue = array();

	/**
	 * Stack of classes/methods/functions currently being parsed.
	 *
	 * @var Node[]
	 */
	protected $location = array();

	/**
	 * Last DocBlock associated with a non-documentable element.
	 *
	 * @var Node\Comment\Doc|null
	 */
	protected $last_doc = null;

	/**
	 * The PHP parser instance.
	 *
	 * @var \PhpParser\Parser
	 */
	protected $parser;

	/**
	 * The PHPDoc parser instance.
	 *
	 * @var PhpDocParser
	 */
	protected $phpdoc_parser;

	/**
	 * The pretty printer for code.
	 *
	 * @var PrettyPrinter
	 */
	protected $pretty_printer;

	/**
	 * File content being parsed.
	 *
	 * @var string
	 */
	protected $content;

	/**
	 * File path being parsed.
	 *
	 * @var string
	 */
	protected $file_path;

	/**
	 * Parsed functions from the file.
	 *
	 * @var array
	 */
	public $functions = array();

	/**
	 * Parsed classes from the file.
	 *
	 * @var array
	 */
	public $classes = array();

	/**
	 * Current namespace context.
	 *
	 * @var string|null
	 */
	protected $current_namespace = null;

	/**
	 * Initialize the file reflector.
	 *
	 * @param string $file_path Path to the file to parse.
	 * @param string $content   File content to parse.
	 */
	public function __construct( $file_path, $content ) {
		$this->file_path = $file_path;
		$this->content = $content;

		// Initialize PHP parser
		$parser_factory = new ParserFactory();
		$this->parser = $parser_factory->createForNewestSupportedVersion();

		// Initialize PHPDoc parser
		$config = new ParserConfig( usedAttributes: [] );
		$constExprParser = new ConstExprParser( $config );
		$typeParser = new TypeParser( $config, $constExprParser );
		$this->phpdoc_parser = new PhpDocParser( $config, $typeParser, $constExprParser );

		// Initialize pretty printer
		$this->pretty_printer = new PrettyPrinter();

		$this->uses = array(
			'hooks' => array(),
			'functions' => array(),
			'methods' => array(),
		);
	}

	/**
	 * Parse the file and extract all elements.
	 *
	 * @return array Parsed data structure.
	 */
	public function parse() {
		try {
			$statements = $this->parser->parse( $this->content );
			if ( null === $statements ) {
				return array();
			}

			// Extract file-level docblock - check first statement for leading comments
			$file_docblock = null;
			if ( ! empty( $statements ) ) {
				$first_stmt = $statements[0];
				$comments = $first_stmt->getAttribute( 'comments' );
				
				if ( $comments ) {
					// Take the first docblock comment as file-level
					$first_comment = $comments[0];
					if ( $first_comment instanceof \PhpParser\Comment\Doc ) {
						$file_docblock = $this->parseDocComment( $first_comment );
					}
				}
			}

			$traverser = new NodeTraverser();
			$traverser->addVisitor( $this );
			$traverser->traverse( $statements );

			return array(
				'functions' => $this->functions,
				'classes' => $this->classes,
				'uses' => $this->uses,
				'file_docblock' => $file_docblock,
			);
		} catch ( \Exception $e ) {
			// Log error and return empty result
			error_log( 'Parse error in ' . $this->file_path . ': ' . $e->getMessage() );
			return array(
				'functions' => array(),
				'classes' => array(),
				'uses' => array(),
				'file_docblock' => null,
			);
		}
	}

	/**
	 * Called when entering a node during traversal.
	 *
	 * @param Node $node The node being entered.
	 * @return int|null
	 */
	public function enterNode( Node $node ) {
		switch ( $node->getType() ) {
			// Track namespace declarations
			case 'Stmt_Namespace':
				if ( $node->name ) {
					$this->current_namespace = $node->name->toString();
				} else {
					$this->current_namespace = null;
				}
				break;

			// Add classes, functions, and methods to the current location stack
			case 'Stmt_Class':
			case 'Stmt_Function':
			case 'Stmt_ClassMethod':
				array_push( $this->location, $node );
				break;

			// Parse out hook definitions and function calls
			case 'Expr_FuncCall':
				$function = new Function_Call_Reflector( $node );

				// Add the call to the list of functions used in this scope
				$location = $this->getLocation();
				if ( ! isset( $location->uses ) ) {
					$location->uses = array( 'functions' => array(), 'hooks' => array(), 'methods' => array() );
				}
				if ( ! isset( $location->uses['functions'] ) ) {
					$location->uses['functions'] = array();
				}
				$location->uses['functions'][] = $function;

				if ( $this->isFilter( $node ) ) {
					if ( $this->last_doc && ! $node->getDocComment() ) {
						$node->setAttribute( 'comments', array( $this->last_doc ) );
						$this->last_doc = null;
					}

					$hook = new Hook_Reflector( $node );

					// Add it to the list of hooks used in this scope
					$location = $this->getLocation();
					if ( ! isset( $location->uses ) ) {
						$location->uses = array( 'functions' => array(), 'hooks' => array(), 'methods' => array() );
					}
					if ( ! isset( $location->uses['hooks'] ) ) {
						$location->uses['hooks'] = array();
					}
					$location->uses['hooks'][] = $hook;
				}
				break;

			// Parse out method calls
			case 'Expr_MethodCall':
				$method = new Method_Call_Reflector( $node );
				// Set class context for $this resolution
				$current_class = $this->getCurrentClass();
				if ( $current_class ) {
					$method->set_class( $current_class );
				}
				$location = $this->getLocation();
				if ( ! isset( $location->uses ) ) {
					$location->uses = array( 'functions' => array(), 'hooks' => array(), 'methods' => array() );
				}
				if ( ! isset( $location->uses['methods'] ) ) {
					$location->uses['methods'] = array();
				}
				$location->uses['methods'][] = $method;
				break;

			// Parse out static method calls
			case 'Expr_StaticCall':
				$method = new Static_Method_Call_Reflector( $node );
				// Set class context for self/parent resolution
				$current_class = $this->getCurrentClass();
				if ( $current_class ) {
					$method->set_class( $current_class );
				}
				$location = $this->getLocation();
				if ( ! isset( $location->uses ) ) {
					$location->uses = array( 'functions' => array(), 'hooks' => array(), 'methods' => array() );
				}
				if ( ! isset( $location->uses['methods'] ) ) {
					$location->uses['methods'] = array();
				}
				$location->uses['methods'][] = $method;
				break;

			// Parse out `new Class()` calls as uses of Class::__construct()
			case 'Expr_New':
				$method = new Method_Call_Reflector( $node );
				// Set class context for $this resolution
				$current_class = $this->getCurrentClass();
				if ( $current_class ) {
					$method->set_class( $current_class );
				}
				$location = $this->getLocation();
				if ( ! isset( $location->uses ) ) {
					$location->uses = array( 'functions' => array(), 'hooks' => array(), 'methods' => array() );
				}
				if ( ! isset( $location->uses['methods'] ) ) {
					$location->uses['methods'] = array();
				}
				$location->uses['methods'][] = $method;
				break;
		}

		// Pick up DocBlock from non-documentable elements
		if ( ! $this->isNodeDocumentable( $node ) && 
			 'Name' !== $node->getType() && 
			 ( $docblock = $node->getDocComment() ) ) {
			$this->last_doc = $docblock;
		}

		return null;
	}

	/**
	 * Called when leaving a node during traversal.
	 *
	 * @param Node $node The node being left.
	 * @return int|null
	 */
	public function leaveNode( Node $node ) {
		switch ( $node->getType() ) {
			case 'Stmt_Class':
				// Process class and assign queued methods
				$class_data = $this->processClass( $node );
				if ( $class_data !== null ) {
					$this->classes[] = $class_data;
				}

				$this->method_uses_queue = array();
				array_pop( $this->location );
				break;

			case 'Stmt_Function':
				// Process function
				$function_node = array_pop( $this->location );
				$function_data = $this->processFunction( $node );
				
				if ( isset( $function_node->uses ) && ! empty( $function_node->uses ) ) {
					$function_data['uses'] = $function_node->uses;
				}
				
				$this->functions[] = $function_data;
				break;

			case 'Stmt_ClassMethod':
				$method_node = array_pop( $this->location );

				// Store the list of elements used by this method in the queue
				if ( ! empty( $method_node->uses ) ) {
					$this->method_uses_queue[ $node->name->toString() ] = $method_node->uses;
				}
				break;
		}

		return null;
	}

	/**
	 * Process a class node and extract class information.
	 *
	 * @param Node\Stmt\Class_ $node The class node.
	 * @return array Class data.
	 */
	protected function processClass( Node\Stmt\Class_ $node ) {
		// Skip anonymous classes (where name is null)
		if ( ! $node->name ) {
			return null;
		}

		$docblock = $this->parseDocComment( $node->getDocComment() );

		return array(
			'name' => $node->name->toString(),
			'line' => $node->getStartLine(),
			'end_line' => $node->getEndLine(),
			'final' => $node->isFinal(),
			'abstract' => $node->isAbstract(),
			'extends' => $node->extends ? $node->extends->toString() : '',
			'implements' => $node->implements ? array_map( fn($impl) => $impl->toString(), $node->implements ) : array(),
			'docblock' => $docblock,
			'methods' => $this->processClassMethods( $node ),
			'properties' => $this->processClassProperties( $node ),
			'namespace' => $this->getCurrentNamespace(),
		);
	}

	/**
	 * Process a function node and extract function information.
	 *
	 * @param Node\Stmt\Function_ $node The function node.
	 * @return array Function data.
	 */
	protected function processFunction( Node\Stmt\Function_ $node ) {
		$docblock = $this->parseDocComment( $node->getDocComment() );

		return array(
			'name' => $node->name->toString(),
			'line' => $node->getStartLine(),
			'end_line' => $node->getEndLine(),
			'docblock' => $docblock,
			'namespace' => $this->getCurrentNamespace(),
			'parameters' => $this->processParameters( $node->params ),
		);
	}

	/**
	 * Process class methods.
	 *
	 * @param Node\Stmt\Class_ $node The class node.
	 * @return array Methods data.
	 */
	protected function processClassMethods( Node\Stmt\Class_ $node ) {
		$methods = array();

		foreach ( $node->getMethods() as $method ) {
			$docblock = $this->parseDocComment( $method->getDocComment() );
			$method_name = $method->name->toString();

			$method_data = array(
				'name' => $method_name,
				'line' => $method->getStartLine(),
				'end_line' => $method->getEndLine(),
				'docblock' => $docblock,
				'visibility' => $this->getMethodVisibility( $method ),
				'static' => $method->isStatic(),
				'parameters' => $this->processParameters( $method->params ),
			);

			// Add queued uses for this method
			if ( isset( $this->method_uses_queue[ $method_name ] ) ) {
				$method_data['uses'] = $this->method_uses_queue[ $method_name ];
			}

			$methods[] = $method_data;
		}

		return $methods;
	}

	/**
	 * Process class properties.
	 *
	 * @param Node\Stmt\Class_ $node The class node.
	 * @return array Properties data.
	 */
	protected function processClassProperties( Node\Stmt\Class_ $node ) {
		$properties = array();

		foreach ( $node->stmts as $stmt ) {
			if ( $stmt instanceof Node\Stmt\Property ) {
				foreach ( $stmt->props as $prop ) {
					$docblock = $this->parseDocComment( $stmt->getDocComment() );

					$property_data = array(
						'name' => '$' . $prop->name->toString(),
						'line' => $stmt->getStartLine(),
						'end_line' => $stmt->getEndLine(),
						'docblock' => $docblock,
						'visibility' => $this->getPropertyVisibility( $stmt ),
						'static' => $stmt->isStatic(),
						'default' => $prop->default ? $this->pretty_printer->prettyPrintExpr( $prop->default ) : null,
					);

					$properties[] = $property_data;
				}
			}
		}

		return $properties;
	}

	/**
	 * Process function/method parameters.
	 *
	 * @param Node\Param[] $params Parameter nodes.
	 * @return array Parameters data.
	 */
	protected function processParameters( array $params ) {
		$parameters = array();

		foreach ( $params as $param ) {
			$param_data = array(
				'name' => $param->var->name,
				'line' => $param->getStartLine(),
				'type' => $param->type ? $this->pretty_printer->prettyPrint( array( $param->type ) ) : null,
				'default' => $param->default ? $this->pretty_printer->prettyPrintExpr( $param->default ) : null,
			);

			$parameters[] = $param_data;
		}

		return $parameters;
	}

	/**
	 * Get method visibility.
	 *
	 * @param Node\Stmt\ClassMethod $method Method node.
	 * @return string Visibility (public, protected, private).
	 */
	protected function getMethodVisibility( Node\Stmt\ClassMethod $method ) {
		if ( $method->isPrivate() ) {
			return 'private';
		}
		if ( $method->isProtected() ) {
			return 'protected';
		}
		return 'public';
	}

	/**
	 * Get property visibility.
	 *
	 * @param Node\Stmt\Property $property Property node.
	 * @return string Visibility (public, protected, private).
	 */
	protected function getPropertyVisibility( Node\Stmt\Property $property ) {
		if ( $property->isPrivate() ) {
			return 'private';
		} elseif ( $property->isProtected() ) {
			return 'protected';
		}

		return 'public';
	}

	/**
	 * Parse a DocComment using phpstan/phpdoc-parser.
	 *
	 * @param Node\Comment\Doc|null $doc_comment DocComment node.
	 * @return array|null Parsed docblock data.
	 */
	protected function parseDocComment( $doc_comment ) {
		if ( ! $doc_comment ) {
			return null;
		}

		try {
			$config = new ParserConfig( usedAttributes: [] );
			$lexer = new Lexer( $config );
			$tokens = $lexer->tokenize( $doc_comment->getText() );
			$token_iterator = new TokenIterator( $tokens );
			$phpdoc_node = $this->phpdoc_parser->parse( $token_iterator );

			return $this->convertPhpDocToArray( $phpdoc_node );
		} catch ( \Exception $e ) {
			error_log( 'DocBlock parse error: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Convert PHPStan PhpDocNode to array format.
	 *
	 * @param PhpDocNode $phpdoc_node The parsed PHPDoc node.
	 * @return array Converted docblock data.
	 */
	protected function convertPhpDocToArray( PhpDocNode $phpdoc_node ) {
		$docblock_data = array(
			'summary' => '',
			'description' => '',
			'tags' => array(),
		);

		// Extract summary and description from text nodes
		$text_content = '';
		foreach ( $phpdoc_node->children as $child ) {
			if ( $child instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode ) {
				$text_content .= $child->text . "\n";
			}
		}

		// Split into summary and description
		$text_lines = explode( "\n", trim( $text_content ) );
		if ( ! empty( $text_lines ) ) {
			// Summary is the first lines before a blank line
			$summary = '';
			while ( ( $line = array_shift( $text_lines ) ) && $line ) {
				$summary .= $line . ' ';
			}
			$docblock_data['summary'] = trim( $summary );

			// The rest is the description.
			$docblock_data['description'] = trim( implode( "\n", $text_lines ) );
		}

		// Extract tags
		foreach ( $phpdoc_node->getTags() as $tag ) {
			$tag_name = ltrim( $tag->name, '@' );
			$value    = $tag->value ? (string) $tag->value : '';

			// Append @type tags to the previous @return or @param tag, not as separate tags.
			// @see https://github.com/WordPress/wporg-developer/blob/bcb196110099a2cd898230834022b6237917e793/source/wp-content/themes/wporg-developer-2023/inc/formatting.php#L598-L680
			if ( 'type' === $tag_name ) {
				// Check @return first (since it comes after @param in docblocks)
				$last_return_index = count( $docblock_data['tags']['return'] ?? array() ) - 1;
				if ( $last_return_index >= 0 ) {
					$docblock_data['tags']['return'][ $last_return_index ] .= "\n@{$tag_name} {$value}";
					continue;
				}

				// Fall back to @param
				$last_param_index = count( $docblock_data['tags']['param'] ?? array() ) - 1;
				if ( $last_param_index >= 0 ) {
					$docblock_data['tags']['param'][ $last_param_index ] .= "\n@{$tag_name} {$value}";
					continue;
				}
			}

			$docblock_data['tags'][ $tag_name ][] = $value;
		}

		return $docblock_data;
	}

	/**
	 * Check if a function call node represents a WordPress filter/action.
	 *
	 * @param Node\Expr\FuncCall $node Function call node.
	 * @return bool True if it's a filter/action.
	 */
	protected function isFilter( Node\Expr\FuncCall $node ) {
		// Ignore variable functions
		if ( ! $node->name instanceof Node\Name ) {
			return false;
		}

		$calling = $node->name->toString();

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
	 * Get the current location in the parsing stack.
	 *
	 * @return File_Reflector|Node Current location object.
	 */
	protected function getLocation() {
		return empty( $this->location ) ? $this : end( $this->location );
	}

	/**
	 * Check if a node is documentable (has meaningful documentation).
	 *
	 * @param Node $node The node to check.
	 * @return bool True if the node is documentable.
	 */
	protected function isNodeDocumentable( Node $node ) {
		return $node instanceof Node\Stmt\Function_
			|| $node instanceof Node\Stmt\Class_
			|| $node instanceof Node\Stmt\ClassMethod
			|| $node instanceof Node\Stmt\Property
			|| $node instanceof Node\Stmt\ClassConst
			|| ( $node instanceof Node\Expr\FuncCall && $this->isFilter( $node ) );
	}

	/**
	 * Get the current namespace context.
	 *
	 * @return string|null Current namespace or null if global scope.
	 */
	protected function getCurrentNamespace() {
		return $this->current_namespace;
	}

	/**
	 * Get the current class context from the location stack.
	 *
	 * @return Node\Stmt\Class_|null Current class node or null if not in a class.
	 */
	protected function getCurrentClass() {
		// Look through the location stack for the most recent class
		for ( $i = count( $this->location ) - 1; $i >= 0; $i-- ) {
			if ( $this->location[$i] instanceof Node\Stmt\Class_ ) {
				return $this->location[$i];
			}
		}
		return null;
	}
}
