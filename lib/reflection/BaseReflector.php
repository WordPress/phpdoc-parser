<?php
/**
 * phpDocumentor
 *
 * PHP Version 5.3
 *
 * @author    Mike van Riel <mike.vanriel@naenius.com>
 * @copyright 2010-2012 Mike van Riel / Naenius (http://www.naenius.com)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      http://phpdoc.org
 */

namespace WP_Parser\Reflection;

use Exception;
use InvalidArgumentException;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Context;
use phpDocumentor\Reflection\DocBlock\Location;
use PhpParser\NodeAbstract;
use Psr\Log\LogLevel;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\PrettyPrinterAbstract;

/**
 * Basic reflection providing support for events and basic properties as a
 * DocBlock and names.
 *
 * @author  Mike van Riel <mike.vanriel@naenius.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 * @link    http://phpdoc.org
 */
abstract class BaseReflector extends ReflectionAbstract
{
    /** @var \PhpParser\Node\Stmt */
    protected $node;

    /**
     * The package name that is passed on by the parent Reflector.
     *
     * May be overwritten and should be passed on to children supporting
     * packages.
     *
     * @var string
     */
    protected $default_package_name = '';

    /**
     * PHP AST pretty printer used to get representations of values.
     *
     * @var \PhpParser\PrettyPrinterAbstract
     */
    protected static $prettyPrinter = null;

    /**
     * Initializes this reflector with the correct node as produced by
     * PHP-Parser.
     *
     * @param NodeAbstract $node
     * @param Context                $context
     *
     * @link http://github.com/nikic/PHP-Parser
     */
    public function __construct(NodeAbstract $node, Context $context)
    {
        $this->node = $node;
        $context->setLSEN($this->getLSEN());
        $this->context = $context;
    }

    /**
     * Returns the current PHP-Parser node that holds more detailed information
     * about the reflected object. e.g. position in the file and further attributes.
     * @return \PhpParser\Node\Stmt|\PhpParser\NodeAbstract
     */
    public function getNode()
    {
        return $this->node;
    }

    /**
     * Sets the name for the namespace.
     *
     * @param string $namespace
     *
     * @throws InvalidArgumentException if something other than a string is
     *     passed.
     *
     * @return void
     */
    public function setNamespace($namespace)
    {
        if (!is_string($namespace)) {
            throw new InvalidArgumentException(
                'Expected a string for the namespace'
            );
        }

        $this->context->setNamespace($namespace);
    }

    /**
     * Returns the parsed DocBlock.
     *
     * @return DocBlock|null
     */
    public function getDocBlock()
    {
        return $this->extractDocBlock($this->node);
    }

    /**
     * Extracts a parsed DocBlock from an object.
     *
     * @param object $node Any object with a "getDocComment()" method.
     *
     * @return DocBlock|null
     */
    protected function extractDocBlock($node)
    {
        $doc_block = null;
        $comment = $node->getDocComment();
        if ($comment) {
            try {
                $doc_block = new DocBlock(
                    (string) $comment,
                    $this->context,
                    new Location($comment->getStartLine())
                );
            } catch (Exception $e) {
                $this->log($e->getMessage(), LogLevel::CRITICAL);
            }
        }

        return $doc_block;
    }

    /**
     * Returns the name for this Reflector instance.
     *
     * @return string
     */
    public function getName()
    {
        if (isset($this->node->namespacedName)) {
            return '\\'.$this->nameToString($this->node->namespacedName);
        }

        return $this->getShortName();
    }

    /**
     * Returns the last component of a namespaced name as a short form.
     *
     * @return string
     */
    public function getShortName()
    {
		if ( isset($this->node->name) ) {
            if ($this->node->name instanceof Expr) {
                return $this->node->name;
            }

            return $this->nameToString($this->node->name);
		}

        if (isset($this->node->var) && $this->node->var instanceof Expr\Variable) {
            return $this->nameToString($this->node->var->name);
        }

		if (interface_exists('\Stringable') && $this->node instanceof \Stringable){
            return (string) $this->node;
		} elseif (method_exists( $this->node, '__toString')) {
			return (string) $this->node;
		}

		if ($this->node instanceof \PhpParser\Node\Stmt\Class_ && $this->node->isAnonymous()) {
			return 'class@anonymous';
		}
    }

    /**
     * Gets the LSEN.
     *
     * Returns this element's Local Structural Element Name (LSEN). This name
     * consistents of the element's short name, along with punctuation that
     * hints at the kind of structural element. If the structural element is
     * part of a type (i.e. an interface/trait/class' property/method/constant),
     * it also contains the name of the owning type.
     *
     * @return string
     */
    public function getLSEN()
    {
        return '';
    }

    /**
     * Returns the namespace name for this object.
     *
     * If this object does not have a namespace then the word 'global' is
     * returned to indicate a global namespace.
     *
     * @return string
     */
    public function getNamespace()
    {
        if (!isset($this->node->namespacedName)) {
            return $this->context->getNamespace();
        }

        $parts = $this->nameParts($this->node->namespacedName);
        array_pop($parts);

        $namespace = implode('\\', $parts);

        return $namespace ? $namespace : 'global';
    }

    /**
     * Returns a listing of namespace aliases where the key represents the alias
     * and the value the Fully Qualified Namespace Name.
     *
     * @return string[]
     */
    public function getNamespaceAliases()
    {
        return $this->context->getNamespaceAliases();
    }

    /**
     * Sets a listing of namespace aliases.
     *
     * The keys represents the alias name and the value the
     * Fully Qualified Namespace Name (FQNN).
     *
     * @param string[] $aliases
     *
     * @return void
     */
    public function setNamespaceAliases(array $aliases)
    {
        $this->context->setNamespaceAliases($aliases);
    }

    /**
     * Sets the Fully Qualified Namespace Name (FQNN) for an alias.
     *
     * @param string $alias
     * @param string $fqnn
     *
     * @return void
     */
    public function setNamespaceAlias($alias, $fqnn)
    {
        $this->context->setNamespaceAlias($alias, $fqnn);
    }

    /**
     * Returns the line number where this object starts.
     *
     * @return int
     */
    public function getLinenumber()
    {
        return $this->node->getStartLine();
    }

    /**
     * Sets the default package name for this object.
     *
     * If the DocBlock contains a different package name then that overrides
     * this package name.
     *
     * @param string $default_package_name The name of the package as defined
     *     in the PHPDoc Standard.
     *
     * @return void
     */
    public function setDefaultPackageName($default_package_name)
    {
        $this->default_package_name = $default_package_name;
    }

    /**
     * Returns the package name that is default for this element.
     *
     * This value may change after the DocBlock is interpreted. If that contains
     * a package tag then that tag overrides the Default package name.
     *
     * @return string
     */
    public function getDefaultPackageName()
    {
        return $this->default_package_name;
    }

    /**
     * Returns a simple human readable output for a value.
     *
     * @param \PhpParser\Node\Expr $value The value node as provided by
     *     PHP-Parser.
     *
     * @return string
     */
    protected function getRepresentationOfValue(
        ?\PhpParser\Node\Expr $value = null
    ) {
        if (null === $value) {
            return '';
        }

        if (!self::$prettyPrinter) {
            self::$prettyPrinter = new PrettyPrinter();
        }

        return self::$prettyPrinter->prettyPrintExpr($value);
    }

    /**
     * Returns the legacy string form for a type declaration.
     *
     * @param mixed $type
     *
     * @return string
     */
    protected function typeToString($type)
    {
        if (null === $type) {
            return '';
        }

        if ($type instanceof \PhpParser\Node\NullableType) {
            return '?'.$this->typeToString($type->type);
        }

        if (class_exists('\PhpParser\Node\UnionType') && $type instanceof \PhpParser\Node\UnionType) {
            return implode('|', array_map(array($this, 'typeToString'), $type->types));
        }

        if (class_exists('\PhpParser\Node\IntersectionType') && $type instanceof \PhpParser\Node\IntersectionType) {
            return implode('&', array_map(array($this, 'typeToString'), $type->types));
        }

        $type = $this->nameToString($type);
        $lower = strtolower($type);
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

        if ('' === $type || in_array($lower, $built_in_types, true) || 0 === strpos($type, '\\')) {
            return $type;
        }

        return '\\'.$type;
    }
}
