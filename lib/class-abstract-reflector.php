<?php

namespace WP_Parser;

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use PhpParser\Node;

/**
 * Base reflector for a PHP-Parser node.
 *
 * Wraps a node together with the file's namespace context and provides
 * name, namespace, line number, and docblock accessors shared by all of
 * the element reflectors.
 */
abstract class Abstract_Reflector {

	/**
	 * @var Node
	 */
	protected $node;

	/**
	 * Namespace context (namespace and aliases) of the containing file.
	 *
	 * The context object is shared with the File_Reflector and is updated
	 * as the file is traversed.
	 *
	 * @var File_Context
	 */
	protected $context;

	/**
	 * Pretty printer used to render value expressions as source text.
	 *
	 * @var Value_Printer
	 */
	protected static $value_printer = null;

	/**
	 * Shared docblock parser.
	 *
	 * @var DocBlockFactoryInterface
	 */
	protected static $docblock_factory = null;

	/**
	 * @param Node         $node
	 * @param File_Context $context
	 */
	public function __construct( Node $node, File_Context $context ) {
		$this->node    = $node;
		$this->context = $context;
	}

	/**
	 * Returns the shared docblock parser.
	 *
	 * @return DocBlockFactoryInterface
	 */
	public static function get_docblock_factory() {
		if ( null === self::$docblock_factory ) {
			self::$docblock_factory = DocBlockFactory::createInstance();
		}

		return self::$docblock_factory;
	}

	/**
	 * Returns the PHP-Parser node for this element.
	 *
	 * @return Node
	 */
	public function getNode() {
		return $this->node;
	}

	/**
	 * Returns the parsed DocBlock for this element, if present.
	 *
	 * @return DocBlock|null
	 */
	public function getDocBlock() {
		return $this->extractDocBlock( $this->node );
	}

	/**
	 * Extracts a parsed DocBlock from a node.
	 *
	 * @param object $node Any object with a `getDocComment()` method.
	 *
	 * @return DocBlock|null
	 */
	protected function extractDocBlock( $node ) {
		$comment = $node->getDocComment();
		if ( ! $comment ) {
			return null;
		}

		try {
			return self::get_docblock_factory()->create(
				(string) $comment,
				$this->context->getTypeContext()
			);
		} catch ( \Throwable $e ) {
			// Treat an unparsable docblock as no docblock.
			return null;
		}
	}

	/**
	 * Returns the name of this element, fully qualified when known.
	 *
	 * @return string
	 */
	public function getName() {
		if ( isset( $this->node->namespacedName ) ) {
			return '\\' . $this->nameToString( $this->node->namespacedName );
		}

		return $this->getShortName();
	}

	/**
	 * Returns the short (unqualified) name of this element.
	 *
	 * May return the name expression node itself for dynamic names, e.g.
	 * variable function calls; callers handle those forms explicitly.
	 *
	 * @return string|\PhpParser\Node\Expr
	 */
	public function getShortName() {
		if ( isset( $this->node->name ) ) {
			if ( $this->node->name instanceof Node\Expr ) {
				return $this->node->name;
			}

			return $this->nameToString( $this->node->name );
		}

		if ( isset( $this->node->var ) && $this->node->var instanceof Node\Expr\Variable ) {
			return $this->nameToString( $this->node->var->name );
		}

		if ( method_exists( $this->node, '__toString' ) ) {
			return (string) $this->node;
		}

		if ( $this->node instanceof Node\Stmt\Class_ && $this->node->isAnonymous() ) {
			return 'class@anonymous';
		}
	}

	/**
	 * Returns the namespace name for this element.
	 *
	 * Elements resolved to the global namespace report 'global' when their
	 * name is namespace-qualified; elements without a qualified name fall
	 * back to the file context's namespace.
	 *
	 * @return string
	 */
	public function getNamespace() {
		if ( ! isset( $this->node->namespacedName ) ) {
			return $this->context->getNamespace();
		}

		$parts = $this->nameParts( $this->node->namespacedName );
		array_pop( $parts );

		$namespace = implode( '\\', $parts );

		return $namespace ? $namespace : 'global';
	}

	/**
	 * Returns the namespace aliases (alias => fully qualified name) in
	 * effect for this element's file.
	 *
	 * @return string[]
	 */
	public function getNamespaceAliases() {
		return $this->context->getNamespaceAliases();
	}

	/**
	 * Returns the line number where this element starts.
	 *
	 * @return int
	 */
	public function getLineNumber() {
		return $this->node->getStartLine();
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

		if ( $name instanceof Node\Expr\Variable ) {
			return $this->nameToString( $name->name );
		}

		if ( $name instanceof Node\Name ) {
			return implode( '\\', $this->nameParts( $name ) );
		}

		if ( is_array( $name ) ) {
			return implode( '\\', array_map( array( $this, 'nameToString' ), $name ) );
		}

		if ( is_object( $name ) && method_exists( $name, '__toString' ) ) {
			return (string) $name;
		}

		if ( is_object( $name ) ) {
			return get_class( $name );
		}

		return (string) $name;
	}

	/**
	 * Returns the parts of a PHP-Parser Name.
	 *
	 * @param Node\Name $name
	 *
	 * @return string[]
	 */
	protected function nameParts( Node\Name $name ) {
		return $name->getParts();
	}

	/**
	 * Returns the string form for a type declaration.
	 *
	 * Class type names are prefixed with a namespace separator; built-in
	 * type keywords are returned as written.
	 *
	 * @param mixed $type
	 *
	 * @return string
	 */
	protected function typeToString( $type ) {
		if ( null === $type ) {
			return '';
		}

		if ( $type instanceof Node\NullableType ) {
			return '?' . $this->typeToString( $type->type );
		}

		if ( $type instanceof Node\UnionType ) {
			return implode( '|', array_map( array( $this, 'typeToString' ), $type->types ) );
		}

		if ( $type instanceof Node\IntersectionType ) {
			return implode( '&', array_map( array( $this, 'typeToString' ), $type->types ) );
		}

		$type  = $this->nameToString( $type );
		$lower = strtolower( $type );

		$built_in_types = array(
			'array',
			'bool',
			'callable',
			'false',
			'float',
			'int',
			'iterable',
			'mixed',
			'never',
			'null',
			'object',
			'parent',
			'self',
			'static',
			'string',
			'true',
			'void',
			'$this',
		);

		if ( '' === $type || in_array( $lower, $built_in_types, true ) || 0 === strpos( $type, '\\' ) ) {
			return $type;
		}

		return '\\' . $type;
	}

	/**
	 * Returns the source representation of a value expression.
	 *
	 * @param Node\Expr|null $value
	 *
	 * @return string
	 */
	protected function getRepresentationOfValue( ?Node\Expr $value = null ) {
		if ( null === $value ) {
			return '';
		}

		if ( ! self::$value_printer ) {
			self::$value_printer = new Value_Printer();
		}

		return self::$value_printer->prettyPrintExpr( $value );
	}
}
