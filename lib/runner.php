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
 * Parses PHP files into records consumed by the importer.
 *
 * Setup Blueprints flow from file and class DocBlocks to descendants. A
 * descendant copies only definitions referenced by one of its snippets.
 *
 * @param string[] $files PHP source files to parse.
 * @param string   $root  Root path removed from exported file paths.
 *
 * @return array Parsed file records in input order.
 */
function parse_files( $files, $root ) {
	$output = array();

	foreach ( $files as $filename ) {
		$file = new File_Reflector( $filename );

		$path = ltrim( substr( $filename, strlen( $root ) ), DIRECTORY_SEPARATOR );
		$file->setFilename( $path );

		$file->process();

		$file_doc = export_docblock( $file, array(), $path );
		$file_setup_blueprints = isset( $file_doc['setup_blueprints'] ) ? $file_doc['setup_blueprints'] : array();

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
			$class_setup_blueprints = array_merge( $file_setup_blueprints, isset( $class_doc['setup_blueprints'] ) ? $class_doc['setup_blueprints'] : array() );

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
 * Exports one reflected DocBlock and its runnable snippet metadata.
 *
 * Fenced descriptions are recovered from source because phpDocumentor may
 * interpret PHP lines beginning with `@` as tags. Named setup references may
 * resolve against definitions inherited from the enclosing file or class.
 *
 * @param BaseReflector|ReflectionAbstract $element                    Reflected DocBlock owner.
 * @param array                            $inherited_setup_blueprints Setup Blueprints visible from enclosing scopes.
 * @param string                           $source_file                Source path used in metadata errors.
 *
 * @throws \InvalidArgumentException When snippet metadata is invalid or ambiguous.
 *
 * @return array Exported descriptions, tags, snippets, and referenced setup Blueprints.
 */
function export_docblock( $element, array $inherited_setup_blueprints = array(), $source_file = '' ) {
	$node_docblock          = null;
	$node_source_docblock   = null;
	$docblock_was_sanitized = $element instanceof File_Reflector && $element->wasDocBlockSanitized();
	if ( ! ( $element instanceof File_Reflector ) && method_exists( $element, 'getNode' ) ) {
		$node = $element->getNode();
		if ( $node && method_exists( $node, 'getDocComment' ) ) {
			$node_docblock = $node->getDocComment();
			if ( $node_docblock ) {
				$node_source_docblock = (string) $node_docblock;
			}
		}
	}

	$docblock = $element->getDocBlock();
	if ( ! $docblock && null !== $node_source_docblock && false !== strpos( $node_source_docblock, '```' ) ) {
		/*
		 * phpDocumentor does not recognize fences. A fenced line beginning with `@`
		 * starts its tag block, and a later PHP expression such as `@! file_exists()`
		 * can make the whole DocBlock fail to parse.
		 *
		 * Blank only the bodies of complete fences and retry, leaving the fence
		 * markers and line structure intact. Restore the original AST comment
		 * immediately afterward. The parsed object supplies tags and namespace
		 * context; the untouched $node_source_docblock supplies descriptions and
		 * snippet code below.
		 */
		$sanitized_docblock = sanitize_docblock_fenced_contents( $node_source_docblock );
		if ( $sanitized_docblock !== $node_source_docblock ) {
			$node_docblock->setText( $sanitized_docblock );
			$docblock = $element->getDocBlock();
			$node_docblock->setText( $node_source_docblock );
			$docblock_was_sanitized = (bool) $docblock;
		}
	}
	if ( ! $docblock ) {
		return array(
			'description'      => '',
			'long_description' => '',
			'tags'             => array(),
		);
	}
	$fenced_docblock_tag_names = array();

	try {
		$short_description    = $docblock->getShortDescription();
		$raw_long_description = $docblock->getLongDescription()->getContents();
		$source_docblock       = null;

		/*
		 * phpDocumentor can split one fenced block across its short and long
		 * descriptions, alter its line structure, and parse `@` code as tags. For
		 * example, this source:
		 *
		 *     ```php interactive
		 *     <?php
		 *
		 *     echo 'before';
		 *     @unlink( '/tmp/example' );
		 *     ```
		 *
		 * can leave the opening lines in the short description, the `echo` line in the
		 * long description, and `@unlink` in the tag list. If either description
		 * contains a possible fence, recover the original DocBlock before tokenizing
		 * it below. File reflectors require slicing the comment from the file contents;
		 * node-backed reflectors use the raw comment captured above.
		 */
		if ( false !== strpos( $short_description, '```' ) || false !== strpos( $raw_long_description, '```' ) ) {
			if ( $element instanceof File_Reflector ) {
				$location = $docblock->getLocation();
				if ( $location && $location->getLineNumber() ) {
					$source_lines      = explode( "\n", preg_replace( "/\r\n?/", "\n", $element->getContents() ) );
					$source_line       = $location->getLineNumber() - 1;
					$source_line_count = count( $source_lines );
					if ( isset( $source_lines[ $source_line ] ) ) {
						$opening = strpos( $source_lines[ $source_line ], '/**' );
						if ( false !== $opening ) {
							$source_lines[ $source_line ] = substr( $source_lines[ $source_line ], $opening );
							$source_docblock_lines = array();
							for ( ; $source_line < $source_line_count; $source_line++ ) {
								$source_docblock_lines[] = $source_lines[ $source_line ];
								if ( false !== strpos( $source_lines[ $source_line ], '*/' ) ) {
									break;
								}
							}
							$source_docblock = implode( "\n", $source_docblock_lines );
						}
					}
				}
			} elseif ( null !== $node_source_docblock ) {
				$source_docblock = $node_source_docblock;
			}
		}

		if ( null !== $source_docblock ) {
			/*
			 * Recover exact description lines from source and stop at the first tag
			 * outside a fence. For example:
			 *
			 *     ```php
			 *     @since( 'inside-fence' );
			 *     ```
			 *     @since 1.0.0
			 *
			 * The first `@since` remains snippet code; the second ends the description.
			 * Record the first name so only phpDocumentor's false in-fence tag is
			 * removed from the parsed tags, leaving the real `@since 1.0.0` tag.
			 */
			$source_docblock = preg_replace( "/\r\n?/", "\n", $source_docblock );
			$source_docblock = preg_replace( '/\A[ \t]*\/\*\*[ \t]?/', '', $source_docblock );
			$source_docblock = preg_replace( '/[ \t]*\*\/[ \t]*\z/', '', $source_docblock );
			$source_lines    = explode( "\n", $source_docblock );
			foreach ( $source_lines as $key => $source_line ) {
				$source_lines[ $key ] = preg_replace( '/^[ \t]*\*[ \t]?/', '', $source_line );
			}

			// Reuse tokenizer boundaries so source recovery and snippet export agree
			// on which exact backtick runs delimit complete fences.
			$source_fences        = tokenize_docblock_code_fences( implode( "\n", $source_lines ) );
			$source_fence_index   = 0;
			$description_lines    = array();
			$parsed_tag_block_started = false;
			foreach ( $source_lines as $source_line_number => $source_line ) {
				while (
					isset( $source_fences[ $source_fence_index ] ) &&
					$source_line_number >= $source_fences[ $source_fence_index ]['end']
				) {
					$source_fence_index++;
				}
				$is_in_fence = isset( $source_fences[ $source_fence_index ] ) &&
					$source_line_number > $source_fences[ $source_fence_index ]['start'];

				/*
				 * Before tag parsing starts, `^[ \t]*` permits indentation and `\pL`
				 * requires a Unicode letter: `  @since` matches, while `@_before` does not.
				 * Once started, `^@` requires column zero and the broader name class permits
				 * an initial underscore or digit: `@_same` and `@2inside` match, while
				 * `  @author` remains part of the preceding tag.
				 */
				$tag_pattern = $parsed_tag_block_started
					? '/^@([\w\-_\\\\]+)/u'
					: '/^[ \t]*@([\pL][\w\-_\\\\]*)/u';
				if ( preg_match( $tag_pattern, $source_line, $tag_match ) ) {
					if ( ! $is_in_fence ) {
						break;
					}
					$parsed_tag_block_started    = true;
					$fenced_docblock_tag_names[] = $tag_match[1];
				}

				$description_lines[] = $source_line;
			}
			// Remove blank wrapper lines without stripping indentation from a fence
			// that begins or ends the description. That indentation controls both
			// content dedenting and the fence's Markdown nesting level.
			$description_edge_pattern = '/\A(?:[ \t]*\n)+|(?:\n[ \t]*)+\z/';
			$source_description        = preg_replace( $description_edge_pattern, '', implode( "\n", $description_lines ) );
			if ( $docblock_was_sanitized ) {
				// Sanitized parsing never created the false in-fence tags, so every
				// parsed tag belongs to the actual DocBlock tag section.
				$fenced_docblock_tag_names = array();
			}

			if ( preg_match( '/(?:\A|\n)[ \t]*`{3,}[^`\n]*(?=\n|\z)/', $short_description ) ) {
				$raw_long_description = $source_description;
				$short_description    = '';
			} elseif ( '' !== $short_description && 0 === strpos( $source_description, $short_description ) ) {
				$raw_long_description = preg_replace( $description_edge_pattern, '', substr( $source_description, strlen( $short_description ) ) );
			} elseif ( '' !== $raw_long_description ) {
				$long_description_start = strpos( $source_description, $raw_long_description );
				if ( false !== $long_description_start ) {
					$raw_long_description = preg_replace( $description_edge_pattern, '', substr( $source_description, $long_description_start ) );
				}
			}
		}

		// phpDocumentor assigns the first DocBlock paragraph to the short
		// description and can split it at a blank line inside a fence. Detect an
		// opening line without requiring its closer, then rejoin both descriptions
		// before parsing so the fence retains its line structure and metadata pairing.
		if ( '' !== $short_description && preg_match( '/(?:\A|\n)[ \t]*`{3,}[^`\n]*(?=\n|\z)/', $short_description ) ) {
			$raw_long_description = $short_description . ( '' === $raw_long_description ? '' : "\n\n" . $raw_long_description );
			$short_description    = '';
		}

		$fences = get_docblock_code_fences( $raw_long_description );

		// Reusing an enclosing name would make the same reference resolve to
		// different setup depending on which DocBlock is being exported.
		foreach ( $fences as $fence ) {
			if (
				null !== $fence['setup_name'] &&
				array_key_exists( $fence['setup_name'], $inherited_setup_blueprints )
			) {
				throw new \InvalidArgumentException(
					'Setup Blueprint "' . $fence['setup_name'] . '" on line ' . ( $fence['start'] + 1 ) .
					' of the long description is already defined in an enclosing DocBlock.'
				);
			}
		}

		$setup_blueprints     = array();
		$code_snippets        = export_docblock_code_snippets( $raw_long_description, $setup_blueprints, $fences );

		// Copy only referenced inherited setups into this DocBlock's output. Each
		// imported post then contains everything its snippets need without copying
		// every file- or class-level setup into every descendant.
		$referenced_inherited_setup_blueprints = array();
		$snippet_lines = array();
		foreach ( $fences as $fence ) {
			if ( $fence['is_interactive_php'] ) {
				$snippet_lines[ $fence['snippet_index'] ] = $fence['start'] + 1;
			}
		}
		foreach ( $code_snippets as $index => $snippet ) {
			if ( ! isset( $snippet['blueprint'] ) || ! is_string( $snippet['blueprint'] ) ) {
				continue;
			}

			if ( array_key_exists( $snippet['blueprint'], $inherited_setup_blueprints ) ) {
				$referenced_inherited_setup_blueprints[ $snippet['blueprint'] ] = $inherited_setup_blueprints[ $snippet['blueprint'] ];
				continue;
			}

			if ( ! array_key_exists( $snippet['blueprint'], $setup_blueprints ) ) {
				throw new \InvalidArgumentException(
					'Setup Blueprint "' . $snippet['blueprint'] . '" referenced on line ' .
					$snippet_lines[ $index ] . ' of the long description is not defined.'
				);
			}
		}
		$setup_blueprints = array_merge( $referenced_inherited_setup_blueprints, $setup_blueprints );
	} catch ( \InvalidArgumentException $exception ) {
		throw new \InvalidArgumentException(
			describe_docblock_source( $element, $docblock, $source_file ) . ': ' . $exception->getMessage(),
			0,
			$exception
		);
	}

	$output = array(
		'description'      => preg_replace( '/[\n\r]+/', ' ', $short_description ),
		'long_description' => format_long_description( strip_docblock_code_snippet_fences( $raw_long_description, $fences ) ),
		'tags'             => array(),
	);

	if ( ! empty( $code_snippets ) ) {
		$output['code_snippets'] = $code_snippets;
	}
	if ( ! empty( $setup_blueprints ) ) {
		$output['setup_blueprints'] = $setup_blueprints;
	}

	$fenced_docblock_tag_counts = array_count_values( $fenced_docblock_tag_names );
	foreach ( $docblock->getTags() as $tag ) {
		if ( ! empty( $fenced_docblock_tag_counts[ $tag->getName() ] ) ) {
			$fenced_docblock_tag_counts[ $tag->getName() ]--;
			continue;
		}

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
 * Returns a parser-safe DocBlock with complete fence bodies replaced by blanks.
 *
 * phpDocumentor does not recognize Markdown fences. It can mistake a fenced
 * `@unlink(...)` call for a DocBlock tag, then reject a later expression such as
 * `@! file_exists(...)` as a malformed tag. For example, these comment lines:
 *
 *     * ```php
 *     * @unlink( '/tmp/example' );
 *     * @! file_exists( '/tmp/example' );
 *     * ```
 *     * @since 1.0.0
 *
 * are returned as:
 *
 *     * ```php
 *     *
 *     *
 *     * ```
 *     * @since 1.0.0
 *
 * The fence delimiters and physical line count remain. Decorated body lines
 * retain their indentation and `*`, and tags outside fences remain unchanged.
 * phpDocumentor can therefore parse the real `@since` tag, while callers read
 * descriptions and snippets from the original DocBlock. Keeping the fence
 * delimiters also tells export_docblock() to recover that original source.
 *
 * An unmatched outer fence is not blanked because its body boundary is unknown.
 *
 * @param string $source_docblock Raw DocBlock including comment delimiters.
 *
 * @return string Parser-safe DocBlock, or the unchanged input when it contains
 *                no complete fence.
 */
function sanitize_docblock_fenced_contents( $source_docblock ) {
	$original_source_docblock = $source_docblock;

	// Normalize line endings so tokenizer indexes map to physical source lines.
	$source_docblock          = preg_replace( "/\r\n?/", "\n", $source_docblock );

	// Remove the opener and at most one decorative whitespace byte.
	$contents                 = preg_replace( '/\A[ \t]*\/\*\*[ \t]?/', '', $source_docblock );

	// Remove only the end-anchored closing delimiter and its indentation.
	$contents                 = preg_replace( '/[ \t]*\*\/[ \t]*\z/', '', $contents );
	$content_lines            = explode( "\n", $contents );
	foreach ( $content_lines as $key => $line ) {
		// Remove the decorative star and at most one following whitespace byte.
		$content_lines[ $key ] = preg_replace( '/^[ \t]*\*[ \t]?/', '', $line );
	}

	$fences = tokenize_docblock_code_fences( implode( "\n", $content_lines ) );
	if ( empty( $fences ) ) {
		return $original_source_docblock;
	}

	$source_lines = explode( "\n", $source_docblock );
	foreach ( $fences as $fence ) {
		for ( $line = $fence['start'] + 1; $line < $fence['end']; $line++ ) {
			// Retain indentation and the decorative star while blanking body text.
			$source_lines[ $line ] = preg_match( '/^([ \t]*\*)/', $source_lines[ $line ], $prefix ) ? $prefix[1] : '';
		}
	}

	return implode( "\n", $source_lines );
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
	if ( preg_match( '/<!--[ \t]wp-parser-code-snippet(?:-placeholder)?:[0-9]+[ \t]-->/', $text ) ) {
		throw new \InvalidArgumentException(
			'The DocBlock placeholder comment syntax is reserved for generated snippet placement.'
		);
	}

	$fences = tokenize_docblock_code_fences( $text );

	foreach ( $fences as $key => $fence ) {
		$info_parts = '' === $fence['info'] ? array() : preg_split( '/\s+/', $fence['info'] );

		// Match the complete public grammar before validating any option value.
		// Setup-looking text on a non-interactive PHP fence remains ordinary
		// documentation and must not make an existing DocBlock fail to parse.
		$referenced_setup   = null;
		$is_interactive_php = false;
		if ( 'php' === $fence['language'] && isset( $info_parts[1] ) && 'interactive' === $info_parts[1] ) {
			if ( 2 === count( $info_parts ) ) {
				$is_interactive_php = true;
			} elseif ( 3 === count( $info_parts ) && 0 === strpos( $info_parts[2], 'setup-blueprint=' ) ) {
				$referenced_setup = substr( $info_parts[2], strlen( 'setup-blueprint=' ) );
				validate_docblock_setup_blueprint_name( $referenced_setup, $fence['start'] );
				$is_interactive_php = true;
			}
		}

		$setup_name = null;
		if ( 'setup-blueprint' === $fence['language'] && 2 === count( $info_parts ) ) {
			$setup_name = $info_parts[1];
			validate_docblock_setup_blueprint_name( $setup_name, $fence['start'] );
		}

		$is_expected_output = 'expected-output' === $fence['language'] && 1 === count( $info_parts );
		$is_blueprint       = 'setup-blueprint' === $fence['language'] && 1 === count( $info_parts );
		$fences[ $key ]['referenced_setup']   = $referenced_setup;
		$fences[ $key ]['is_interactive_php'] = $is_interactive_php;
		$fences[ $key ]['is_expected_output'] = $is_expected_output;
		$fences[ $key ]['is_blueprint']       = $is_blueprint;
		$fences[ $key ]['setup_name']         = $setup_name;
		$fences[ $key ]['is_code_snippet']    = $is_interactive_php || $is_expected_output || $is_blueprint || null !== $setup_name;
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
 * Tokenizes complete backtick fences without interpreting their info strings.
 *
 * The raw-source recovery path needs fence boundaries before phpDocumentor has
 * successfully parsed the comment. Keeping that lexical pass separate prevents
 * invalid snippet metadata from escaping before export_docblock() can add source
 * context to the resulting error.
 *
 * @param string $text Raw DocBlock contents or long description.
 *
 * @return array
 */
function tokenize_docblock_code_fences( $text ) {
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
			// Content may be less indented than its fence, so remove as much of the
			// opening prefix as each line repeats. Stop where tabs and spaces differ
			// rather than guessing that unlike whitespace occupies equal columns.
			foreach ( $code_lines as $key => $code_line ) {
				$remove_length = 0;
				$max_length    = min( strlen( $indent ), strlen( $code_line ) );
				while ( $remove_length < $max_length && $indent[ $remove_length ] === $code_line[ $remove_length ] ) {
					$remove_length++;
				}

				if ( 0 < $remove_length ) {
					$code_lines[ $key ] = substr( $code_line, $remove_length );
				}
			}
		}

		$info     = trim( $opening[3] );
		$language = $info;
		if ( preg_match( '/^\S+/', $language, $language_matches ) ) {
			$language = $language_matches[0];
		}

		$fence = array(
			'language' => $language,
			'info'     => $info,
			'code'     => rtrim( implode( "\n", $code_lines ), "\n" ),
			'start'    => $line_no,
			'end'      => $end,
		);

		$fences[] = $fence;

		$line_no = $end;
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
 * Reusable setup Blueprint names use lowercase kebab-case starting with a letter.
 *
 * @param string     $text             Raw DocBlock long description.
 * @param array      $setup_blueprints Optional. Named setup Blueprints keyed by reference name.
 * @param array|null $fences           Optional. Fences already parsed from the same description.
 *
 * @throws \InvalidArgumentException When snippet metadata is invalid, ambiguous, or unattached.
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
	$setup_blueprint_lines   = array();

	foreach ( $fences as $fence ) {
		if ( null !== $fence['setup_name'] ) {
			if ( array_key_exists( $fence['setup_name'], $setup_blueprints ) ) {
				throw new \InvalidArgumentException(
					'Setup Blueprint "' . $fence['setup_name'] . '" is defined more than once on lines ' .
					$setup_blueprint_lines[ $fence['setup_name'] ] . ' and ' . ( $fence['start'] + 1 ) .
					' of the long description.'
				);
			}

			$setup_blueprints[ $fence['setup_name'] ] = decode_docblock_blueprint( $fence['code'], $fence );
			$setup_blueprint_lines[ $fence['setup_name'] ] = $fence['start'] + 1;
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
			if (
				null !== $pending_blueprint &&
				docblock_fences_have_only_whitespace_between( $fences[ $pending_blueprint_fence ], $fences[ $i ], $lines )
			) {
				throw new \InvalidArgumentException(
					'Interactive snippet has more than one setup Blueprint: fences on lines ' .
					( $fences[ $pending_blueprint_fence ]['start'] + 1 ) . ' and ' . ( $fences[ $i ]['start'] + 1 ) .
					' of the long description cannot both precede one snippet.'
				);
			}

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
			// A snippet accepts one setup source. Failing here prevents a named
			// reference from silently overriding an adjacent inline Blueprint.
			if ( array_key_exists( 'blueprint', $snippet ) ) {
				throw new \InvalidArgumentException(
					'Interactive PHP fence on line ' . ( $fences[ $i ]['start'] + 1 ) .
					' of the long description has more than one setup Blueprint.'
				);
			}
			$snippet['blueprint'] = $pending_blueprint;
			$consumed_fences[ $pending_blueprint_fence ] = true;
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

			if ( $fences[ $j ]['is_blueprint'] ) {
				if ( array_key_exists( 'blueprint', $snippet ) ) {
					throw new \InvalidArgumentException(
						'Interactive PHP fence on line ' . ( $fences[ $i ]['start'] + 1 ) .
						' of the long description has more than one setup Blueprint.'
					);
				}

				$snippet['blueprint']  = decode_docblock_blueprint( $fences[ $j ]['code'], $fences[ $j ] );
				$consumed_fences[ $j ] = true;
				$previous_fence        = $j;
				continue;
			}

			break;
		}

		$snippets[] = $snippet;
	}

	// Recognized metadata is reserved for runnable snippets. Rejecting orphaned
	// fences avoids removing author-written content without exporting it anywhere.
	foreach ( $fences as $index => $fence ) {
		if ( isset( $consumed_fences[ $index ] ) ) {
			continue;
		}

		if ( $fence['is_expected_output'] ) {
			throw new \InvalidArgumentException(
				'Expected-output fence on line ' . ( $fence['start'] + 1 ) .
				' of the long description is not attached to an interactive PHP fence.'
			);
		}

		if ( $fence['is_blueprint'] ) {
			throw new \InvalidArgumentException(
				'Inline setup Blueprint on line ' . ( $fence['start'] + 1 ) .
				' of the long description is not attached to an interactive PHP fence.'
			);
		}
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
 * @param string     $text   Raw DocBlock long description.
 * @param array|null $fences Optional. Classified fences already validated by the snippet exporter.
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

		// Interactive PHP fences become `code_snippets` entries. A plain HTML
		// comment survives Markdown rendering, `the_content`, and block parsing,
		// allowing the theme to replace it in place between the surrounding prose.
		// Snippet-metadata fences (expected-output, Blueprints) are removed.
		for ( $i = $fence['start']; $i <= $fence['end']; $i++ ) {
			if ( $fence['is_interactive_php'] && $i === $fence['start'] ) {
				// Keep a nested fence's indentation so Markdown leaves the replacement
				// inside its list item instead of closing the list around the snippet.
				$indent = substr( $lines[ $i ], 0, strspn( $lines[ $i ], " \t" ) );
				$replace_lines[ $i ] = $indent . '<!-- wp-parser-code-snippet-placeholder:' . (int) $fence['snippet_index'] . ' -->';
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
 * Decodes a Blueprint fence into the structure exported to JSON.
 *
 * @param string $blueprint Blueprint JSON.
 * @param array  $fence     Optional. Parsed fence used to identify invalid input.
 *
 * @throws \InvalidArgumentException When the Blueprint is not a valid JSON object.
 *
 * @return array|\stdClass
 */
function decode_docblock_blueprint( $blueprint, $fence = null ) {
	$decoded = json_decode( $blueprint );
	$label   = 'Setup Blueprint';

	if ( is_array( $fence ) ) {
		if ( null !== $fence['setup_name'] ) {
			$label .= ' "' . $fence['setup_name'] . '"';
		}
		$label .= ' on line ' . ( $fence['start'] + 1 ) . ' of the long description';
	}

	if ( JSON_ERROR_NONE !== json_last_error() ) {
		$error = function_exists( 'json_last_error_msg' ) ? json_last_error_msg() : 'error code ' . json_last_error();
		throw new \InvalidArgumentException( $label . ' must contain valid JSON: ' . $error );
	}

	if ( ! is_object( $decoded ) ) {
		throw new \InvalidArgumentException( $label . ' must be a JSON object.' );
	}

	preserve_json_object_shapes( $decoded );
	return $decoded;
}

/**
 * Preserves JSON objects that associative decoding would turn into lists.
 *
 * Most JSON objects naturally become associative PHP arrays and serialize back
 * as objects. Empty objects and objects with sequential numeric keys instead
 * serialize as JSON lists unless they remain objects. Decoding as objects first
 * supplies that distinction. This function converts ordinary named objects to
 * the associative arrays expected by the importer while retaining objects that
 * would change type when encoded again.
 *
 * @param mixed $value JSON value decoded as objects.
 *
 * @return mixed
 */
function preserve_json_object_shapes( &$value ) {
	if ( is_object( $value ) ) {
		$decoded = get_object_vars( $value );
		foreach ( $decoded as &$child ) {
			preserve_json_object_shapes( $child );
		}
		unset( $child );

		if ( empty( $decoded ) || array_keys( $decoded ) === range( 0, count( $decoded ) - 1 ) ) {
			$value = (object) $decoded;
		} else {
			$value = $decoded;
		}

		return $value;
	}

	if ( is_array( $value ) ) {
		foreach ( $value as &$child ) {
			preserve_json_object_shapes( $child );
		}
		unset( $child );
	}

	return $value;
}

/**
 * Rejects reusable setup Blueprint names outside the documented kebab-case form.
 *
 * Requiring a leading letter prevents PHP from coercing a numeric name into an
 * integer array key and changing the setup Blueprint map into a JSON list.
 *
 * @param string $name    Setup Blueprint name.
 * @param int    $line_no Zero-based long-description line containing the name.
 *
 * @throws \InvalidArgumentException When the name is not lowercase kebab-case.
 */
function validate_docblock_setup_blueprint_name( $name, $line_no ) {
	if ( preg_match( '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $name ) ) {
		return;
	}

	throw new \InvalidArgumentException(
		'Setup Blueprint name "' . $name . '" on line ' . ( $line_no + 1 ) .
		' of the long description must be lowercase kebab-case starting with a letter.'
	);
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

	/*
	 * Fences may use more than three leading spaces. Parsedown puts adjacent
	 * indented placeholders into one code block, including the blank lines left
	 * by removed metadata fences. Unwrap only a code block made entirely of our
	 * intermediate markers, then publish the final comments consumed by the theme.
	 * Keeping the intermediate name distinct also prevents author-written final
	 * comments from being mistaken for generated placement markers here. The
	 * whitespace runs are possessive because a marker begins with `&`, so a long
	 * indented near-match never needs whitespace backtracking.
	 */
	$description = preg_replace_callback(
		'#<pre><code>((?:[ \t\n]*+&lt;!-- wp-parser-code-snippet-placeholder:[0-9]+ --&gt;)+[ \t\n]*+)</code></pre>#',
		function ( $matches ) {
			$placeholders = str_replace( array( '&lt;', '&gt;' ), array( '<', '>' ), trim( $matches[1] ) );
			return preg_replace( '/[ \t\n]++(?=<!-- wp-parser-code-snippet-placeholder:)/', "\n", $placeholders );
		},
		$description
	);
	$description = preg_replace(
		'/<!-- wp-parser-code-snippet-placeholder:([0-9]+) -->/',
		'<!-- wp-parser-code-snippet:$1 -->',
		$description
	);

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
