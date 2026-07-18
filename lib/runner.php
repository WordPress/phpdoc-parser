<?php

namespace WP_Parser;

use phpDocumentor\Reflection\BaseReflector;
use phpDocumentor\Reflection\ClassReflector;
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

		$file_doc = export_docblock( $file, array(), $path );
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
			$out['hooks'] = export_hooks( $file->uses['hooks'], $file_setup_blueprints, $path );
		}

		foreach ( $file->getFunctions() as $function ) {
			$func = array(
				'name'      => $function->getShortName(),
				'namespace' => $function->getNamespace(),
				'aliases'   => $function->getNamespaceAliases(),
				'line'      => $function->getLineNumber(),
				'end_line'  => $function->getNode()->getAttribute( 'endLine' ),
				'arguments' => export_arguments( $function->getArguments() ),
				'doc'       => export_docblock( $function, $file_setup_blueprints, $path ),
				'hooks'     => array(),
			);

			if ( ! empty( $function->uses ) ) {
				$func['uses'] = export_uses( $function->uses );

				if ( ! empty( $function->uses['hooks'] ) ) {
					$func['hooks'] = export_hooks( $function->uses['hooks'], $file_setup_blueprints, $path );
				}
			}

			$out['functions'][] = $func;
		}

		foreach ( $file->getClasses() as $class ) {
			$class_doc = export_docblock( $class, $file_setup_blueprints, $path );
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
				'properties' => export_properties( $class->getProperties(), $class_setup_blueprints, $path ),
				'methods'    => export_methods( $class->getMethods(), $class_setup_blueprints, $path ),
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
 * @param string                           $source_file                Optional. Source path used in invalid snippet metadata errors.
 *
 * @return array
 */
function export_docblock( $element, array $inherited_setup_blueprints = array(), $source_file = '' ) {
	$docblock = $element->getDocBlock();
	if ( ! $docblock ) {
		return array(
			'description'      => '',
			'long_description' => '',
			'tags'             => array(),
		);
	}

	try {
		$raw_long_description = $docblock->getLongDescription()->getContents();
		$fences               = get_docblock_code_fences( $raw_long_description );
		$setup_blueprints     = array();
		$code_snippets        = export_docblock_code_snippets( $raw_long_description, $setup_blueprints, $fences );
		$setup_blueprints     = array_merge(
			get_referenced_setup_blueprints( $code_snippets, $inherited_setup_blueprints ),
			$setup_blueprints
		);
		validate_docblock_setup_blueprint_references( $code_snippets, $setup_blueprints );
	} catch ( \InvalidArgumentException $exception ) {
		throw new \InvalidArgumentException(
			describe_docblock_source( $element, $docblock, $source_file ) . ': ' . $exception->getMessage(),
			0,
			$exception
		);
	}

	$output = array(
		'description'      => preg_replace( '/[\n\r]+/', ' ', $docblock->getShortDescription() ),
		'long_description' => format_long_description( strip_docblock_code_snippet_fences( $raw_long_description, $fences ) ),
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
 * Describes the source DocBlock that contains invalid snippet metadata.
 *
 * @param BaseReflector|ReflectionAbstract  $element
 * @param \phpDocumentor\Reflection\DocBlock $docblock
 * @param string                            $source_file Optional source path.
 *
 * @return string
 */
function describe_docblock_source( $element, $docblock, $source_file = '' ) {
	if ( $element instanceof File_Reflector ) {
		$entity = 'file';
	} elseif ( $element instanceof Hook_Reflector ) {
		$entity = 'hook "' . $element->getName() . '"';
	} elseif ( $element instanceof PropertyReflector ) {
		$entity = 'property "' . $element->getName() . '"';
	} elseif ( $element instanceof MethodReflector ) {
		$entity = 'method "' . $element->getShortName() . '"';
	} elseif ( $element instanceof FunctionReflector ) {
		$entity = 'function "' . $element->getShortName() . '"';
	} elseif ( $element instanceof ClassReflector ) {
		$entity = 'class "' . $element->getShortName() . '"';
	} else {
		$entity = 'element';
	}

	$source = '' !== $source_file ? ' in ' . $source_file : '';
	if ( $docblock->getLocation() && $docblock->getLocation()->getLineNumber() ) {
		$source .= ' starting on source line ' . $docblock->getLocation()->getLineNumber();
	}

	return 'DocBlock for ' . $entity . $source;
}

/**
 * @param Hook_Reflector[] $hooks
 * @param array            $inherited_setup_blueprints Optional. Setup Blueprints inherited from the enclosing file or class.
 * @param string           $source_file                Optional. Source path used in invalid snippet metadata errors.
 *
 * @return array
 */
function export_hooks( array $hooks, array $inherited_setup_blueprints = array(), $source_file = '' ) {
	$out = array();

	foreach ( $hooks as $hook ) {
		$out[] = array(
			'name'      => $hook->getName(),
			'line'      => $hook->getLineNumber(),
			'end_line'  => $hook->getNode()->getAttribute( 'endLine' ),
			'type'      => $hook->getType(),
			'arguments' => $hook->getArgs(),
			'doc'       => export_docblock( $hook, $inherited_setup_blueprints, $source_file ),
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
 * @param array               $inherited_setup_blueprints Optional. Setup Blueprints inherited from the file or class DocBlock.
 * @param string              $source_file                Optional. Source path used in invalid snippet metadata errors.
 *
 * @return array
 */
function export_properties( array $properties, array $inherited_setup_blueprints = array(), $source_file = '' ) {
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
			'doc'         => export_docblock( $property, $inherited_setup_blueprints, $source_file ),
		);
	}

	return $out;
}

/**
 * @param MethodReflector[] $methods
 * @param array             $inherited_setup_blueprints Optional. Setup Blueprints inherited from the file or class DocBlock.
 * @param string            $source_file                Optional. Source path used in invalid snippet metadata errors.
 *
 * @return array
 */
function export_methods( array $methods, array $inherited_setup_blueprints = array(), $source_file = '' ) {
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
			'doc'        => export_docblock( $method, $inherited_setup_blueprints, $source_file ),
		);

		if ( ! empty( $method->uses ) ) {
			$method_data['uses'] = export_uses( $method->uses );

			if ( ! empty( $method->uses['hooks'] ) ) {
				$method_data['hooks'] = export_hooks( $method->uses['hooks'], $inherited_setup_blueprints, $source_file );
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
	$text       = preg_replace( "/\r\n?/", "\n", $text );
	$lines      = explode( "\n", $text );
	$line_count = count( $lines );
	$fences     = array();

	// Advance the outer cursor to each matching closer. Every line is examined
	// at most once, and matching does not depend on PCRE recursion or JIT stack
	// size. An opener without a matching closer stops parsing so later fence-like
	// lines remain part of that unterminated block.
	for ( $line_no = 0; $line_no < $line_count; $line_no++ ) {
		if ( ! preg_match( '/^([ \t]*)(`{3,})([^`]*)$/', $lines[ $line_no ], $opening ) ) {
			continue;
		}

		$indent         = $opening[1];
		$backticks       = $opening[2];
		$closing_pattern = '/^[ \t]*' . preg_quote( $backticks, '/' ) . '[ \t]*$/';
		$end             = $line_no + 1;

		while ( $end < $line_count && ! preg_match( $closing_pattern, $lines[ $end ] ) ) {
			$end++;
		}

		if ( $end === $line_count ) {
			break;
		}

		$code_lines = array_slice( $lines, $line_no + 1, $end - $line_no - 1 );
		if ( '' !== $indent ) {
			// Strip the opening fence's indentation from each content line.
			foreach ( $code_lines as $key => $code_line ) {
				if ( 0 === strpos( $code_line, $indent ) ) {
					$code_lines[ $key ] = substr( $code_line, strlen( $indent ) );
				}
			}
		}

		$language = trim( $opening[3] );
		if ( preg_match( '/^\S+/', $language, $language_matches ) ) {
			$language = $language_matches[0];
		}

		$fence = array(
			'language' => $language,
			'info'     => trim( $opening[3] ),
			'code'     => rtrim( implode( "\n", $code_lines ), "\n" ),
			'start'    => $line_no,
			'end'      => $end,
		);

		// Classify each fence once here so the snippet exporter and the
		// description stripper share the result instead of recomputing it.
		$info_parts                  = get_docblock_fence_info_parts( $fence );
		$fence['referenced_setup']   = get_docblock_referenced_blueprint_name( $fence );
		$fence['is_interactive_php'] = 'php' === $fence['language']
			&& isset( $info_parts[1] )
			&& 'interactive' === $info_parts[1]
			&& ( 2 === count( $info_parts ) || null !== $fence['referenced_setup'] );
		$fence['is_expected_output'] = is_docblock_expected_output_fence( $fence );
		$fence['is_blueprint']       = is_docblock_blueprint_fence( $fence );
		$fence['setup_name']         = get_docblock_setup_blueprint_name( $fence );
		$fence['is_code_snippet']    = $fence['is_interactive_php'] || $fence['is_expected_output'] || $fence['is_blueprint'] || null !== $fence['setup_name'];

		$fences[] = $fence;

		$line_no = $end;
	}

	// Number the interactive PHP fences so the exporter and the stripper agree on each
	// snippet's index without counting independently.
	$snippet_index = 0;
	foreach ( $fences as $key => $fence ) {
		$fences[ $key ]['snippet_index'] = $fence['is_interactive_php'] ? $snippet_index++ : null;
	}

	return $fences;
}

/**
 * Extract PHP fences marked `interactive` from a DocBlock's raw long description.
 *
 * Backtick fences may be indented in DocBlocks or nested Markdown lists. The
 * closing fence must use the same number of backticks as the opener so
 * different-length fences can appear inside a fenced snippet. Blueprint fences
 * before a PHP fence apply to that fence, while immediately following metadata
 * fences apply to the preceding PHP fence. Named setup Blueprint fences are
 * exported once and snippets refer to them by name. Fence info words are
 * case-sensitive so the documented lowercase forms are the only accepted syntax.
 *
 * @param string $text             Raw DocBlock long description.
 * @param array  $setup_blueprints Optional. Named setup Blueprints keyed by reference name.
 *
 * @throws \InvalidArgumentException When a setup Blueprint is not a valid JSON object.
 *
 * @return array
 */
function export_docblock_code_snippets( $text, &$setup_blueprints = null, $fences = null ) {
	if ( null === $fences ) {
		$fences = get_docblock_code_fences( $text );
	}
	$lines    = explode( "\n", preg_replace( "/\r\n?/", "\n", $text ) );
	$snippets = array();

	$pending_blueprint       = null;
	$pending_blueprint_fence = null;
	$consumed_fences         = array();
	$fence_count             = count( $fences );
	$setup_blueprints        = array();

	foreach ( $fences as $fence ) {
		if ( null !== $fence['setup_name'] ) {
			$setup_blueprints[ $fence['setup_name'] ] = decode_docblock_blueprint( $fence['code'], $fence );
		}
	}

	for ( $i = 0; $i < $fence_count; $i++ ) {
		if ( isset( $consumed_fences[ $i ] ) ) {
			continue;
		}

		if ( null !== $fences[ $i ]['setup_name'] ) {
			continue;
		}

		if ( $fences[ $i ]['is_blueprint'] ) {
			$pending_blueprint       = decode_docblock_blueprint( $fences[ $i ]['code'], $fences[ $i ] );
			$pending_blueprint_fence = $i;
			continue;
		}

		if ( ! $fences[ $i ]['is_interactive_php'] ) {
			$pending_blueprint       = null;
			$pending_blueprint_fence = null;
			continue;
		}

		$snippet = array(
			'type' => 'php-code-snippet',
			'code' => $fences[ $i ]['code'],
		);

		if ( null !== $fences[ $i ]['referenced_setup'] ) {
			$snippet['blueprint'] = $fences[ $i ]['referenced_setup'];
		}

		if (
			null !== $pending_blueprint &&
			docblock_fences_have_only_whitespace_between( $fences[ $pending_blueprint_fence ], $fences[ $i ], $lines )
		) {
			if ( ! array_key_exists( 'blueprint', $snippet ) ) {
				$snippet['blueprint'] = $pending_blueprint;
			}
		}
		$pending_blueprint       = null;
		$pending_blueprint_fence = null;

		$previous_fence = $i;
		for ( $j = $i + 1; $j < $fence_count; $j++ ) {
			if ( ! docblock_fences_have_only_whitespace_between( $fences[ $previous_fence ], $fences[ $j ], $lines ) ) {
				break;
			}

			if ( $fences[ $j ]['is_interactive_php'] ) {
				break;
			}

			if ( $fences[ $j ]['is_expected_output'] ) {
				// First expected-output fence ends the run, so a snippet takes one.
				$snippet['expected_output'] = $fences[ $j ]['code'];
				$consumed_fences[ $j ]      = true;
				break;
			}

			if ( null !== $fences[ $j ]['setup_name'] ) {
				break;
			}

			if ( $fences[ $j ]['is_blueprint'] && ! array_key_exists( 'blueprint', $snippet ) ) {
				$snippet['blueprint']  = decode_docblock_blueprint( $fences[ $j ]['code'], $fences[ $j ] );
				$consumed_fences[ $j ] = true;
				$previous_fence        = $j;
				continue;
			}

			break;
		}

		$snippets[] = $snippet;
	}

	return $snippets;
}

/**
 * Checks whether two fences are separated only by blank DocBlock lines.
 *
 * Metadata may be visually separated from its snippet by blank lines, but
 * prose between them starts a new documentation section and ends the pairing.
 *
 * @param array $first  Earlier parsed fence.
 * @param array $second Later parsed fence.
 * @param array $lines  Normalized DocBlock long-description lines.
 *
 * @return bool
 */
function docblock_fences_have_only_whitespace_between( $first, $second, $lines ) {
	for ( $line = $first['end'] + 1; $line < $second['start']; $line++ ) {
		if ( '' !== trim( $lines[ $line ] ) ) {
			return false;
		}
	}

	return true;
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
function strip_docblock_code_snippet_fences( $text, $fences = null ) {
	$text  = preg_replace( "/\r\n?/", "\n", $text );
	$lines = explode( "\n", $text );
	if ( null === $fences ) {
		$fences = get_docblock_code_fences( $text );
	}
	$remove_lines  = array();
	$replace_lines = array();

	foreach ( $fences as $fence ) {
		if ( ! $fence['is_code_snippet'] ) {
			continue;
		}

		// Interactive PHP fences become `code_snippets` entries; replace each one with an
		// inline placeholder, keyed by the fence's shared snippet index, so the
		// theme renders the runnable snippet in place between the surrounding
		// prose. Snippet-metadata fences (expected-output, Blueprints) are removed.
		for ( $i = $fence['start']; $i <= $fence['end']; $i++ ) {
			if ( $fence['is_interactive_php'] && $i === $fence['start'] ) {
				$replace_lines[ $i ] = docblock_code_snippet_placeholder( $fence['snippet_index'] );
			} else {
				$remove_lines[ $i ] = true;
			}
		}
	}

	foreach ( $lines as $line_number => $line ) {
		if ( isset( $replace_lines[ $line_number ] ) ) {
			$lines[ $line_number ] = $replace_lines[ $line_number ];
		} elseif ( isset( $remove_lines[ $line_number ] ) ) {
			unset( $lines[ $line_number ] );
		}
	}

	return trim( implode( "\n", $lines ) );
}

/**
 * Inline placeholder left in `long_description` for the Nth PHP code snippet.
 *
 * A plain HTML comment so it survives Markdown rendering, `the_content`, and the
 * block parser untouched; the theme replaces it with the rendered runnable
 * snippet, keeping snippets positioned between the surrounding prose.
 *
 * @param int $index Zero-based index into `code_snippets`.
 * @return string
 */
function docblock_code_snippet_placeholder( $index ) {
	return '<!-- wp-parser-code-snippet:' . (int) $index . ' -->';
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
 * Rejects snippet references that do not resolve to an available setup Blueprint.
 *
 * @param array $snippets         Exported code snippets.
 * @param array $setup_blueprints Setup Blueprints available to the DocBlock.
 *
 * @throws \InvalidArgumentException When a snippet references an undefined setup Blueprint.
 */
function validate_docblock_setup_blueprint_references( $snippets, $setup_blueprints ) {
	foreach ( $snippets as $snippet ) {
		if (
			is_string( $snippet['blueprint'] ?? null ) &&
			! array_key_exists( $snippet['blueprint'], $setup_blueprints )
		) {
			throw new \InvalidArgumentException( 'Setup Blueprint "' . $snippet['blueprint'] . '" is not defined.' );
		}
	}
}

/**
 * Checks whether a parsed DocBlock fence contains snippet expected output.
 *
 * @param array $fence
 *
 * @return bool
 */
function is_docblock_expected_output_fence( $fence ) {
	return 'expected-output' === $fence['language'] && 1 === count( get_docblock_fence_info_parts( $fence ) );
}

/**
 * Checks whether a parsed DocBlock fence contains a WordPress Playground Blueprint.
 *
 * @param array $fence
 *
 * @return bool
 */
function is_docblock_blueprint_fence( $fence ) {
	return 'setup-blueprint' === $fence['language'] && 1 === count( get_docblock_fence_info_parts( $fence ) );
}

/**
 * Decodes a Blueprint fence into the structure exported to JSON.
 *
 * @param string $blueprint Blueprint JSON.
 * @param array  $fence     Optional. Parsed fence used to identify invalid input.
 *
 * @throws \InvalidArgumentException When the Blueprint is not a valid JSON object.
 *
 * @return array
 */
function decode_docblock_blueprint( $blueprint, $fence = null ) {
	$decoded = json_decode( $blueprint, true );
	$label   = 'Setup Blueprint';

	if ( is_array( $fence ) ) {
		if ( null !== $fence['setup_name'] ) {
			$label .= ' "' . $fence['setup_name'] . '"';
		}
		$label .= ' on line ' . ( $fence['start'] + 1 ) . ' of the long description';
	}

	if ( JSON_ERROR_NONE !== json_last_error() ) {
		throw new \InvalidArgumentException( $label . ' must contain valid JSON: ' . json_last_error_msg() );
	}

	if ( '{' !== substr( ltrim( $blueprint ), 0, 1 ) || ! is_array( $decoded ) ) {
		throw new \InvalidArgumentException( $label . ' must be a JSON object.' );
	}

	return $decoded;
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

	if ( 'setup-blueprint' === $fence['language'] && 2 === count( $info_parts ) ) {
		return $info_parts[1];
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
	$info_parts = get_docblock_fence_info_parts( $fence );

	if ( 'php' === $fence['language'] && 3 === count( $info_parts ) && preg_match( '/^setup-blueprint=(.+)$/', $info_parts[2], $matches ) ) {
		return $matches[1];
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
	// Preserve phpDocumentor's established handling of plain HTML code blocks.
	// The snippet parser works from raw contents because it must remove selected
	// fences before Markdown rendering, bypassing getFormattedContents().
	if ( false !== strpos( $description, '<code>' ) ) {
		$description = str_replace(
			array( '<code>', "<code>\r\n", "<code>\n", "<code>\r", '</code>' ),
			array( '<pre><code>', '<code>', '<code>', '<code>', '</code></pre>' ),
			$description
		);
	}

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
