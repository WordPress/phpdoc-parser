<?php

namespace WP_Parser;

function get_wp_files( $directory ) {
	$iterableFiles = new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator( $directory )
	);
	$files         = array();

	try {
		foreach ( $iterableFiles as $file ) {
			if ( $file->getExtension() !== 'php' ) {
				continue;
			}

			$files[] = $file->getPathname();
		}
	} catch ( \UnexpectedValueException $e ) {
		printf( 'Directory [%s] contained a directory we can not recurse into', $directory );
	}

	return $files;
}

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
			$cl = array(
				'name'       => $class->getShortName(),
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

			$out['classes'][] = $cl;
		}

		$output[] = $out;
	}

	return $output;
}

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
		$t = array(
			'name'    => $tag->getName(),
			'content' => preg_replace( '/[\n\r]+/', ' ', $tag->getDescription() ),
		);
		if ( method_exists( $tag, 'getTypes' ) ) {
			$t['types'] = $tag->getTypes();
		}
		if ( method_exists( $tag, 'getVariableName' ) ) {
			$t['variable'] = $tag->getVariableName();
		}
		if ( method_exists( $tag, 'getReference' ) ) {
			$t['refers'] = $tag->getReference();
		}
		if ( 'since' == $tag->getName() && method_exists( $tag, 'getVersion' ) ) {
			// Version string.
			$version = $tag->getVersion();
			if ( !empty( $version ) ) {
				$t['content'] = $version;
			}
			// Description string.
			if ( method_exists( $tag, 'getDescription' ) ) {
				$description = preg_replace( '/[\n\r]+/', ' ', $tag->getDescription() );
				if ( ! empty( $description ) ) {
					$t['description'] = $description;
				}
			}
		}
		$output['tags'][] = $t;
	}

	return $output;
}

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

function export_properties( array $properties ) {
	$out = array();

	foreach ( $properties as $property ) {
		$prop = array(
			'name'        => $property->getName(),
			'line'        => $property->getLineNumber(),
			'end_line'    => $property->getNode()->getAttribute( 'endLine' ),
			'default'     => $property->getDefault(),
//			'final' => $property->isFinal(),
			'static'      => $property->isStatic(),
			'visibililty' => $property->getVisibility(),
		);

		$docblock = export_docblock( $property );
		if ( $docblock ) {
			$prop['doc'] = $docblock;
		}

		$out[] = $prop;

	}

	return $out;
}

function export_methods( array $methods ) {
	$out = array();

	foreach ( $methods as $method ) {
		$meth = array(
			'name'       => $method->getShortName(),
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
			$meth['uses'] = export_uses( $method->uses );

			if ( ! empty( $method->uses['hooks'] ) ) {
				$meth['hooks'] = export_hooks( $method->uses['hooks'] );
			}
		}

		$out[] = $meth;
	}

	return $out;
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
		foreach ( $used_elements as $element ) {
			$out[ $type ][] = array(
				'name'       => $element->getName(),
				'line'       => $element->getLineNumber(),
				'end_line'   => $element->getNode()->getAttribute( 'endLine' ),
			);
		}
	}

	return $out;
}
