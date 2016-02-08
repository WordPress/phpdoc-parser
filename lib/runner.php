<?php

namespace WP_Parser;

use phpDocumentor\Reflection\BaseReflector;
use phpDocumentor\Reflection\ClassReflector\MethodReflector;
use phpDocumentor\Reflection\ClassReflector\PropertyReflector;
use phpDocumentor\Reflection\FunctionReflector;
use phpDocumentor\Reflection\FunctionReflector\ArgumentReflector;
use phpDocumentor\Reflection\ReflectionAbstract;

/**
 * @param string $directory
 *
 * @return array|\WP_Error
 */
function get_wp_files( $directory ) {
	$iterableFiles = new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator( $directory )
	);
	$files         = array();

	try {
		foreach ( $iterableFiles as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}

			$files[] = $file->getPathname();
		}
	} catch ( \UnexpectedValueException $exc ) {
		return new \WP_Error(
			'unexpected_value_exception',
			sprintf( 'Directory [%s] contained a directory we can not recurse into', $directory )
		);
	}

	return $files;
}

/**
 * @param array  $files
 * @param string $root
 *
 * @return array
 */
function parse_files( $files, $root ) {
	$output = array();

	foreach ( $files as $filename ) {
		$file = new File_Reflector( $filename );

		$path = ltrim( substr( $filename, strlen( $root ) ), DIRECTORY_SEPARATOR );
		$file->setFilename( $path );

		$file->process();

		// TODO proper exporter
		$out = array(
			'file' => export_docblock( $file ),
			'path' => str_replace( DIRECTORY_SEPARATOR, '/', $file->getFilename() ),
			'root' => $root,
		);

		if ( ! empty( $file->uses ) ) {
			$out['uses'] = export_uses( $file->uses );
		}

		foreach ( $file->getIncludes() as $include ) {
			$out['includes'][] = array(
				'name' => $include->getName(),
				'line' => $include->getLineNumber(),
				'type' => $include->getType(),
			);
		}

		foreach ( $file->getConstants() as $constant ) {
			$out['constants'][] = array(
				'name'  => $constant->getShortName(),
				'line'  => $constant->getLineNumber(),
				'value' => $constant->getValue(),
			);
		}

		if ( ! empty( $file->uses['hooks'] ) ) {
			$out['hooks'] = export_hooks( $file->uses['hooks'] );
		}

		foreach ( $file->getFunctions() as $function ) {
			$func = array(
				'name'      => $function->getShortName(),
				'namespace' => $function->getNamespace(),
				'aliases'   => $function->getNamespaceAliases(),
				'line'      => $function->getLineNumber(),
				'end_line'  => $function->getNode()->getAttribute( 'endLine' ),
				'arguments' => export_arguments( $function->getArguments() ),
				'doc'       => export_docblock( $function ),
				'hooks'     => array(),
			);

			if ( ! empty( $function->uses ) ) {
				$func['uses'] = export_uses( $function->uses );

				if ( ! empty( $function->uses['hooks'] ) ) {
					$func['hooks'] = export_hooks( $function->uses['hooks'] );
				}
			}

			$out['functions'][] = $func;
		}

		foreach ( $file->getClasses() as $class ) {
			$class_data = array(
				'name'       => $class->getShortName(),
				'namespace'  => $class->getNamespace(),
				'line'       => $class->getLineNumber(),
				'end_line'   => $class->getNode()->getAttribute( 'endLine' ),
				'final'      => $class->isFinal(),
				'abstract'   => $class->isAbstract(),
				'extends'    => $class->getParentClass(),
				'implements' => $class->getInterfaces(),
				'properties' => export_properties( $class->getProperties() ),
				'methods'    => export_methods( $class->getMethods() ),
				'doc'        => export_docblock( $class ),
			);

			$out['classes'][] = $class_data;
		}

		$output[] = $out;
	}

	return $output;
}

/**
 * @param BaseReflector|ReflectionAbstract $element
 *
 * @return array
 */
function export_docblock( $element ) {
	$docblock = $element->getDocBlock();
	if ( ! $docblock ) {
		return array(
			'description'      => '',
			'long_description' => '',
			'tags'             => array(),
		);
	}

	$output = array(
		'description'      => preg_replace( '/[\n\r]+/', ' ', $docblock->getShortDescription() ),
		'long_description' => preg_replace( '/[\n\r]+/', ' ', $docblock->getLongDescription()->getFormattedContents() ),
		'tags'             => array(),
	);

	foreach ( $docblock->getTags() as $tag ) {
		$tag_data = array(
			'name'    => $tag->getName(),
			'content' => preg_replace( '/[\n\r]+/', ' ', format_description( $tag->getDescription() ) ),
		);
		if ( method_exists( $tag, 'getTypes' ) ) {
			$tag_data['types'] = $tag->getTypes();
		}
		if ( method_exists( $tag, 'getVariableName' ) ) {
			$tag_data['variable'] = $tag->getVariableName();
		}
		if ( method_exists( $tag, 'getReference' ) ) {
			$tag_data['refers'] = $tag->getReference();
		}
		if ( method_exists( $tag, 'getVersion' ) ) {
			// Version string.
			$version = $tag->getVersion();
			if ( ! empty( $version ) ) {
				$tag_data['content'] = $version;
			}
			// Description string.
			if ( method_exists( $tag, 'getDescription' ) ) {
				$description = preg_replace( '/[\n\r]+/', ' ', format_description( $tag->getDescription() ) );
				if ( ! empty( $description ) ) {
					$tag_data['description'] = $description;
				}
			}
		}
		$output['tags'][] = $tag_data;
	}

	return $output;
}

/**
 * @param Hook_Reflector[] $hooks
 *
 * @return array
 */
function export_hooks( array $hooks ) {
	$out = array();

	foreach ( $hooks as $hook ) {
		$out[] = array(
			'name'      => $hook->getName(),
			'line'      => $hook->getLineNumber(),
			'end_line'  => $hook->getNode()->getAttribute( 'endLine' ),
			'type'      => $hook->getType(),
			'arguments' => $hook->getArgs(),
			'doc'       => export_docblock( $hook ),
		);
	}

	return $out;
}

/**
 * @param ArgumentReflector[] $arguments
 *
 * @return array
 */
function export_arguments( array $arguments ) {
	$output = array();

	foreach ( $arguments as $argument ) {
		$output[] = array(
			'name'    => $argument->getName(),
			'default' => $argument->getDefault(),
			'type'    => $argument->getType(),
		);
	}

	return $output;
}

/**
 * @param PropertyReflector[] $properties
 *
 * @return array
 */
function export_properties( array $properties ) {
	$out = array();

	foreach ( $properties as $property ) {
		$out[] = array(
			'name'        => $property->getName(),
			'line'        => $property->getLineNumber(),
			'end_line'    => $property->getNode()->getAttribute( 'endLine' ),
			'default'     => $property->getDefault(),
//			'final' => $property->isFinal(),
			'static'      => $property->isStatic(),
			'visibility'  => $property->getVisibility(),
			'doc'         => export_docblock( $property ),
		);
	}

	return $out;
}

/**
 * @param MethodReflector[] $methods
 *
 * @return array
 */
function export_methods( array $methods ) {
	$output = array();

	foreach ( $methods as $method ) {

		$method_data = array(
			'name'       => $method->getShortName(),
			'namespace'  => $method->getNamespace(),
			'aliases'    => $method->getNamespaceAliases(),
			'line'       => $method->getLineNumber(),
			'end_line'   => $method->getNode()->getAttribute( 'endLine' ),
			'final'      => $method->isFinal(),
			'abstract'   => $method->isAbstract(),
			'static'     => $method->isStatic(),
			'visibility' => $method->getVisibility(),
			'arguments'  => export_arguments( $method->getArguments() ),
			'doc'        => export_docblock( $method ),
		);

		if ( ! empty( $method->uses ) ) {
			$method_data['uses'] = export_uses( $method->uses );

			if ( ! empty( $method->uses['hooks'] ) ) {
				$method_data['hooks'] = export_hooks( $method->uses['hooks'] );
			}
		}

		$output[] = $method_data;
	}

	return $output;
}

/**
 * Export the list of elements used by a file or structure.
 *
 * @param array $uses {
 *        @type Function_Call_Reflector[] $functions The functions called.
 * }
 *
 * @return array
 */
function export_uses( array $uses ) {
	$out = array();

	// Ignore hooks here, they are exported separately.
	unset( $uses['hooks'] );

	foreach ( $uses as $type => $used_elements ) {

		/** @var MethodReflector|FunctionReflector $element */
		foreach ( $used_elements as $element ) {

			$name = $element->getName();

			switch ( $type ) {
				case 'methods':
					$out[ $type ][] = array(
						'name'     => $name[1],
						'class'    => $name[0],
						'static'   => $element->isStatic(),
						'line'     => $element->getLineNumber(),
						'end_line' => $element->getNode()->getAttribute( 'endLine' ),
					);
					break;

				default:
				case 'functions':
					$out[ $type ][] = array(
						'name'     => $name,
						'line'     => $element->getLineNumber(),
						'end_line' => $element->getNode()->getAttribute( 'endLine' ),
					);

					if ( '_deprecated_file' === $name || '_deprecated_function' === $name || '_deprecated_argument' === $name ) {
						$arguments = $element->getNode()->args;

						$out[ $type ][0]['deprecation_version'] = $arguments[1]->value->value;
					}

					break;
			}
		}
	}

	return $out;
}

/**
 * Format the given description with Markdown.
 *
 * @param string $description Description.
 * @return string Description as Markdown if the Parsedown class exists, otherwise return
 *                the given description text.
 */
function format_description( $description ) {
	if ( class_exists( 'Parsedown' ) ) {
		$parsedown   = \Parsedown::instance();
		$description = $parsedown->line( $description );
	}
	return $description;
}
