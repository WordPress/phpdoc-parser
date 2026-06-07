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

		$file_doc = export_docblock( $file );
		$file_setup_blueprints = $file_doc['setup_blueprints'] ?? array();

		// TODO proper exporter
		$out = array(
			'file' => $file_doc,
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
				'doc'       => export_docblock( $function, $file_setup_blueprints ),
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
			$class_doc = export_docblock( $class, $file_setup_blueprints );
			$class_setup_blueprints = array_merge( $file_setup_blueprints, $class_doc['setup_blueprints'] ?? array() );

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
				'methods'    => export_methods( $class->getMethods(), $class_setup_blueprints ),
				'doc'        => $class_doc,
			);

			$out['classes'][] = $class_data;
		}

		$output[] = $out;
	}

	return $output;
}

/**
 * Fixes newline handling in parsed text.
 *
 * DocBlock lines, particularly for descriptions, generally adhere to a given character width. For sentences and
 * paragraphs that exceed that width, what is intended as a manual soft wrap (via line break) is used to ensure
 * on-screen/in-file legibility of that text. These line breaks are retained by phpDocumentor. However, consumers
 * of this parsed data may believe the line breaks to be intentional and may display the text as such.
 *
 * This function fixes text by merging consecutive lines of text into a single line. A special exception is made
 * for text appearing in `<code>` and `<pre>` tags, as newlines appearing in those tags are always intentional.
 *
 * @param string $text
 *
 * @return string
 */
function fix_newlines( $text ) {
	// Non-naturally occurring string to use as temporary replacement.
	$replacement_string = '{{{{{}}}}}';

	// Replace newline characters within 'code' and 'pre' tags with replacement string.
	$text = preg_replace_callback(
		"/(<pre><code[^>]*>)(.+)(?=<\/code><\/pre>)/sU",
		function ( $matches ) use ( $replacement_string ) {
			return preg_replace( '/[\n\r]/', $replacement_string, $matches[1] . $matches[2] );
		},
		$text
	);

	// Insert a newline when \n follows `.`.
	$text = preg_replace(
		"/\.[\n\r]+(?!\s*[\n\r])/m",
		'.<br>',
		$text
	);

	// Insert a new line when \n is followed by what appears to be a list.
	$text = preg_replace(
		"/[\n\r]+(\s+[*-] )(?!\s*[\n\r])/m",
		'<br>$1',
		$text
	);

	// Merge consecutive non-blank lines together by replacing the newlines with a space.
	$text = preg_replace(
		"/[\n\r](?!\s*[\n\r])/m",
		' ',
		$text
	);

	// Restore newline characters into code blocks.
	$text = str_replace( $replacement_string, "\n", $text );

	return $text;
}

/**
 * @param BaseReflector|ReflectionAbstract $element
 * @param array                            $inherited_setup_blueprints Optional. Setup Blueprints inherited from the file or class DocBlock.
 *
 * @return array
 */
function export_docblock( $element, array $inherited_setup_blueprints = array() ) {
	$docblock = $element->getDocBlock();
	if ( ! $docblock ) {
		return array(
			'description'      => '',
			'long_description' => '',
			'tags'             => array(),
		);
	}

	$raw_long_description = $docblock->getLongDescription()->getContents();
	$setup_blueprints     = array();
	$code_snippets        = export_docblock_code_snippets( $raw_long_description, $setup_blueprints );
	$setup_blueprints     = array_merge(
		get_referenced_setup_blueprints( $code_snippets, $inherited_setup_blueprints ),
		$setup_blueprints
	);

	$output = array(
		'description'      => preg_replace( '/[\n\r]+/', ' ', $docblock->getShortDescription() ),
		'long_description' => format_long_description( strip_docblock_code_snippet_fences( $raw_long_description ) ),
		'tags'             => array(),
	);

	if ( ! empty( $code_snippets ) ) {
		$output['code_snippets'] = $code_snippets;
	}
	if ( ! empty( $setup_blueprints ) ) {
		$output['setup_blueprints'] = $setup_blueprints;
	}

	foreach ( $docblock->getTags() as $tag ) {
		$tag_data = array(
			'name'    => $tag->getName(),
			'content' => preg_replace( '/[\n\r]+/', ' ', format_description( $tag->getDescription() ) ),
		);
		if ( method_exists( $tag, 'getTypes' ) ) {
			$tag_data['types'] = $tag->getTypes();
		}
		if ( method_exists( $tag, 'getLink' ) ) {
			$tag_data['link'] = $tag->getLink();
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
 * @param array             $inherited_setup_blueprints Optional. Setup Blueprints inherited from the file or class DocBlock.
 *
 * @return array
 */
function export_methods( array $methods, array $inherited_setup_blueprints = array() ) {
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
			'doc'        => export_docblock( $method, $inherited_setup_blueprints ),
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
 * Returns Markdown-like backtick fences from a DocBlock's raw long description.
 *
 * @param string $text Raw DocBlock long description.
 *
 * @return array
 */
function get_docblock_code_fences( $text ) {
	$lines    = explode( "\n", preg_replace( "/\r\n?/", "\n", $text ) );
	$fences   = array();

	for ( $i = 0, $line_count = count( $lines ); $i < $line_count; $i++ ) {
		if ( ! preg_match( '/^([ \t]*)(`{3,})([^`]*)$/', $lines[ $i ], $opening ) ) {
			continue;
		}

		$indent     = $opening[1];
		$fence      = $opening[2];
		$language   = trim( $opening[3] );
		$code_lines = array();
		$start_line = $i;

		for ( $j = $i + 1; $j < $line_count; $j++ ) {
			// Match the exact opening fence so different-length fences stay in the snippet.
			if ( preg_match( '/^[ \t]*' . preg_quote( $fence, '/' ) . '[ \t]*$/', $lines[ $j ] ) ) {
				$i = $j;
				break;
			}

			if ( '' !== $indent && 0 === strpos( $lines[ $j ], $indent ) ) {
				$code_lines[] = substr( $lines[ $j ], strlen( $indent ) );
			} else {
				$code_lines[] = $lines[ $j ];
			}
		}

		if ( $j === $line_count ) {
			break;
		}

		if ( preg_match( '/^\S+/', $language, $language_matches ) ) {
			$language = $language_matches[0];
		}

		$fences[] = array(
			'language' => strtolower( $language ),
			'info'     => trim( $opening[3] ),
			'code'     => rtrim( implode( "\n", $code_lines ), "\n" ),
			'start'    => $start_line,
			'end'      => $i,
		);
	}

	return $fences;
}

/**
 * Extract runnable PHP snippets from a DocBlock's raw long description.
 *
 * Backtick fences may be indented in DocBlocks or nested Markdown lists. The
 * closing fence must use the same number of backticks as the opener so
 * different-length fences can appear inside a fenced snippet. Blueprint fences
 * before a PHP fence apply to that fence, while immediately following metadata
 * fences apply to the preceding PHP fence. Named setup Blueprint fences are
 * exported once and snippets refer to them by name.
 *
 * @param string $text             Raw DocBlock long description.
 * @param array  $setup_blueprints Optional. Named setup Blueprints keyed by reference name.
 *
 * @return array
 */
function export_docblock_code_snippets( $text, &$setup_blueprints = null ) {
	$fences   = get_docblock_code_fences( $text );
	$snippets = array();

	$pending_blueprint = null;
	$consumed_fences   = array();
	$fence_count       = count( $fences );
	$setup_blueprints  = array();

	foreach ( $fences as $fence ) {
		$setup_blueprint_name = get_docblock_setup_blueprint_name( $fence );
		if ( null !== $setup_blueprint_name ) {
			$setup_blueprints[ $setup_blueprint_name ] = decode_docblock_blueprint( $fence['code'] );
		}
	}

	for ( $i = 0; $i < $fence_count; $i++ ) {
		if ( isset( $consumed_fences[ $i ] ) ) {
			continue;
		}

		if ( null !== get_docblock_setup_blueprint_name( $fences[ $i ] ) ) {
			continue;
		}

		if ( is_docblock_blueprint_fence( $fences[ $i ] ) ) {
			$pending_blueprint = decode_docblock_blueprint( $fences[ $i ]['code'] );
			continue;
		}

		if ( 'php' !== $fences[ $i ]['language'] ) {
			$pending_blueprint = null;
			continue;
		}

		$snippet = array(
			'type' => 'php-code-snippet',
			'code' => $fences[ $i ]['code'],
		);
		$has_expected_output = false;

		$referenced_blueprint_name = get_docblock_referenced_blueprint_name( $fences[ $i ] );
		if ( null !== $referenced_blueprint_name ) {
			$snippet['blueprint'] = $referenced_blueprint_name;
		}

		if ( null !== $pending_blueprint ) {
			if ( ! array_key_exists( 'blueprint', $snippet ) ) {
				$snippet['blueprint'] = $pending_blueprint;
			}
			$pending_blueprint    = null;
		}

		for ( $j = $i + 1; $j < $fence_count; $j++ ) {
			if ( 'php' === $fences[ $j ]['language'] ) {
				break;
			}

			if ( is_docblock_expected_output_fence( $fences[ $j ] ) ) {
				if ( ! $has_expected_output ) {
					$snippet['expected_output'] = $fences[ $j ]['code'];
					$has_expected_output        = true;
					$consumed_fences[ $j ]      = true;
				}

				break;
			}

			if ( null !== get_docblock_setup_blueprint_name( $fences[ $j ] ) ) {
				break;
			}

			if ( is_docblock_blueprint_fence( $fences[ $j ] ) && ! array_key_exists( 'blueprint', $snippet ) ) {
				$snippet['blueprint']  = decode_docblock_blueprint( $fences[ $j ]['code'] );
				$consumed_fences[ $j ] = true;
				continue;
			}

			break;
		}

		$snippets[] = $snippet;
	}

	return $snippets;
}

/**
 * Removes snippet and snippet-metadata fences from the rendered description.
 *
 * Once a PHP fence becomes structured `code_snippets` data, leaving the same
 * fence in `long_description` would make the theme render both the raw Markdown
 * code block and the runnable snippet.
 *
 * @param string $text Raw DocBlock long description.
 *
 * @return string
 */
function strip_docblock_code_snippet_fences( $text ) {
	$text         = preg_replace( "/\r\n?/", "\n", $text );
	$lines        = explode( "\n", $text );
	$remove_lines = array();

	foreach ( get_docblock_code_fences( $text ) as $fence ) {
		if ( ! is_docblock_code_snippet_fence( $fence ) ) {
			continue;
		}

		for ( $i = $fence['start']; $i <= $fence['end']; $i++ ) {
			$remove_lines[ $i ] = true;
		}
	}

	foreach ( $lines as $line_number => $line ) {
		if ( isset( $remove_lines[ $line_number ] ) ) {
			unset( $lines[ $line_number ] );
		}
	}

	return trim( implode( "\n", $lines ) );
}

/**
 * Returns inherited setup Blueprints referenced by the snippets.
 *
 * @param array $snippets         Exported code snippets.
 * @param array $setup_blueprints Setup Blueprints available from parent DocBlocks.
 *
 * @return array
 */
function get_referenced_setup_blueprints( $snippets, $setup_blueprints ) {
	$referenced_setup_blueprints = array();

	foreach ( $snippets as $snippet ) {
		if ( ! is_string( $snippet['blueprint'] ?? null ) ) {
			continue;
		}

		if ( array_key_exists( $snippet['blueprint'], $setup_blueprints ) ) {
			$referenced_setup_blueprints[ $snippet['blueprint'] ] = $setup_blueprints[ $snippet['blueprint'] ];
		}
	}

	return $referenced_setup_blueprints;
}

/**
 * Checks whether a parsed DocBlock fence is represented by code snippet JSON.
 *
 * @param array $fence
 *
 * @return bool
 */
function is_docblock_code_snippet_fence( $fence ) {
	return 'php' === $fence['language']
		|| is_docblock_expected_output_fence( $fence )
		|| is_docblock_blueprint_fence( $fence )
		|| null !== get_docblock_setup_blueprint_name( $fence );
}

/**
 * Checks whether a parsed DocBlock fence contains snippet expected output.
 *
 * @param array $fence
 *
 * @return bool
 */
function is_docblock_expected_output_fence( $fence ) {
	return in_array( $fence['language'], array( 'expected-output', 'expected_output', 'output', 'text/expected-output' ), true );
}

/**
 * Checks whether a parsed DocBlock fence contains a WordPress Playground Blueprint.
 *
 * @param array $fence
 *
 * @return bool
 */
function is_docblock_blueprint_fence( $fence ) {
	if ( null !== get_docblock_setup_blueprint_name( $fence ) ) {
		return false;
	}

	$info = strtolower( $fence['info'] );

	return in_array( $fence['language'], array( 'blueprint', 'setup-blueprint', 'setupblueprint' ), true )
		|| ( 'json' === $fence['language'] && false !== strpos( ' ' . $info . ' ', ' blueprint ' ) );
}

/**
 * Decodes a Blueprint fence into the structure exported to JSON.
 *
 * @param string $blueprint
 *
 * @return array|string
 */
function decode_docblock_blueprint( $blueprint ) {
	$decoded = json_decode( $blueprint, true );

	if ( is_array( $decoded ) ) {
		return $decoded;
	}

	return $blueprint;
}

/**
 * Returns the reference name for a reusable setup Blueprint fence.
 *
 * @param array $fence
 *
 * @return string|null
 */
function get_docblock_setup_blueprint_name( $fence ) {
	$info_parts = get_docblock_fence_info_parts( $fence );

	if ( in_array( $fence['language'], array( 'setup-blueprint', 'setupblueprint' ), true ) && isset( $info_parts[1] ) ) {
		return $info_parts[1];
	}

	if ( 'json' === $fence['language'] && isset( $info_parts[1] ) && in_array( strtolower( $info_parts[1] ), array( 'setup-blueprint', 'setupblueprint' ), true ) && isset( $info_parts[2] ) ) {
		return $info_parts[2];
	}

	return null;
}

/**
 * Returns the setup Blueprint reference from a PHP fence info string.
 *
 * @param array $fence
 *
 * @return string|null
 */
function get_docblock_referenced_blueprint_name( $fence ) {
	foreach ( get_docblock_fence_info_parts( $fence ) as $part ) {
		if ( preg_match( '/^(?:blueprint|setup-blueprint|setupblueprint)=(.+)$/i', $part, $matches ) ) {
			return $matches[1];
		}
	}

	return null;
}

/**
 * Splits the full fence info string into whitespace-delimited parts.
 *
 * @param array $fence
 *
 * @return array
 */
function get_docblock_fence_info_parts( $fence ) {
	$info = trim( $fence['info'] );

	if ( '' === $info ) {
		return array();
	}

	return preg_split( '/\s+/', $info );
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

					if ( '_deprecated_file' === $name
						|| '_deprecated_function' === $name
						|| '_deprecated_argument' === $name
						|| '_deprecated_hook' === $name
					) {
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
 * Format the given long description with Markdown blocks.
 *
 * @param string $description Description.
 * @return string Description as Markdown if the Parsedown class exists, otherwise return
 *                the given description text.
 */
function format_long_description( $description ) {
	if ( class_exists( 'Parsedown' ) ) {
		$parsedown   = \Parsedown::instance();
		$description = $parsedown->text( $description );
	}

	$description = fix_newlines( $description );

	return $description;
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

	$description = fix_newlines( $description );

	return $description;
}
