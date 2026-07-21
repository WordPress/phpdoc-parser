<?php

namespace WP_Parser;

use phpDocumentor\Reflection\Types\Context as TypeContext;

/**
 * Mutable namespace context for a file being parsed.
 *
 * Tracks the current namespace and use-statement aliases as the file is
 * traversed. Aliases are stored with a leading namespace separator, the
 * form in which they are exported.
 */
class File_Context {

	/**
	 * The current namespace, without leading or trailing separators.
	 *
	 * @var string
	 */
	private $namespace = '';

	/**
	 * Namespace aliases: alias => fully qualified name with a leading
	 * namespace separator.
	 *
	 * @var string[]
	 */
	private $namespace_aliases = array();

	/**
	 * Cached immutable context for docblock type resolution.
	 *
	 * @var TypeContext|null
	 */
	private $type_context = null;

	/**
	 * @return string
	 */
	public function getNamespace() {
		return $this->namespace;
	}

	/**
	 * Sets the current namespace.
	 *
	 * The keywords 'global' and 'default' are treated as aliases for no
	 * namespace.
	 *
	 * @param string $namespace
	 */
	public function setNamespace( $namespace ) {
		if ( 'global' !== $namespace && 'default' !== $namespace ) {
			$this->namespace = trim( (string) $namespace, '\\' );
		} else {
			$this->namespace = '';
		}

		$this->type_context = null;
	}

	/**
	 * @return string[]
	 */
	public function getNamespaceAliases() {
		return $this->namespace_aliases;
	}

	/**
	 * Registers a namespace alias.
	 *
	 * @param string $alias
	 * @param string $fqnn  Fully qualified name; stored with a leading
	 *                      namespace separator regardless of input form.
	 */
	public function setNamespaceAlias( $alias, $fqnn ) {
		$this->namespace_aliases[ $alias ] = '\\' . trim( (string) $fqnn, '\\' );

		$this->type_context = null;
	}

	/**
	 * Returns the immutable type-resolution context for the current
	 * namespace state, as used by the docblock parser.
	 *
	 * @return TypeContext
	 */
	public function getTypeContext() {
		if ( null === $this->type_context ) {
			$aliases = array();
			foreach ( $this->namespace_aliases as $alias => $fqnn ) {
				$aliases[ $alias ] = ltrim( $fqnn, '\\' );
			}

			$this->type_context = new TypeContext( $this->namespace, $aliases );
		}

		return $this->type_context;
	}
}
