<?php

namespace WP_Parser;

use WP_CLI;

/**
 * Class Runner
 *
 * @package WP_Parser
 */
class Runner {
	// CLI config + config file
	protected $exclude_directories = array( 'vendor', 'vendor_prefixed', 'node_modules', 'tests', 'build' );

	private $exporter;
	private $ignore_files;

	/**
	 * Runner constructor.
	 *
	 * @param array $ignore_files Files to ignore.
	 */
	public function __construct( $ignore_files = array() ) {
		$this->ignore_files = $ignore_files;
		$this->exporter = new Exporter();
	}

	/**
	 * Filters out files that shouldn't be parsed.
	 *
	 * Determines whether the passed file is a directory that hasn't been excluded.
	 * If it's a file, the filter determines whether or not we're dealing with a PHP file.
	 *
	 * @param \SplFileInfo 	$file 		The file to check.
	 * @param string 	 	$key  		The key of the passed file.
	 * @param \Iterator	 	$iterator	The iterator object used to traverse the files.
	 *
	 * @return bool Whether or not the passed file is a valid PHP file.
	 */
	public function filter( $file, $key, $iterator ) {
		if ( $iterator->hasChildren() && ! in_array( $file->getFilename(), $this->ignore_files, true ) ) {
			return true;
		}

		return $file->isFile() && $file->getExtension() === 'php';
	}

	/**
	 * Collects all applicable files for later parsing.
	 *
	 * @param string $directory The directory to get the files from.
	 *
	 * @return array|\WP_Error The parsable files. Returns an error if a directory can't be recursed into.
	 */
	public function get_wp_files( $directory ) {
		$iterableFiles = new \RecursiveDirectoryIterator( $directory, \RecursiveDirectoryIterator::SKIP_DOTS );

		$filteredFiles = new \RecursiveIteratorIterator(
			new \RecursiveCallbackFilterIterator( $iterableFiles, array( $this, 'filter' ) )
		);

		$files = array();

		try {
			/* @var $file \SplFileInfo */
			foreach ( $filteredFiles as $file ) {
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
	 * Parses the passed files and attempts to extract the proper docblocks from the files.
	 *
	 * @param array  $files The files to extract the docblocks from.
	 * @param string $root	The root path where the parsing was initially started in.
	 *
	 * @return array The extracted docblocks.
	 *
	 * @throws \phpDocumentor\Reflection\Exception\UnparsableFile Thrown if the file can't be parsed.
	 * @throws \phpDocumentor\Reflection\Exception\UnreadableFile Thrown if the file can't be read.
	 */
	public function parse_files( $files, $root ) {

		var_dump(get_plugin_data($root));die;

		$output = array();

		foreach ( $files as $filename ) {
			$file = new File_Reflector( $filename );

			$path = ltrim( substr( $filename, strlen( $root ) ), DIRECTORY_SEPARATOR );
			$file->setFilename( $path );
			$file->process();

			$out = array(
				'file' => $this->exporter->export_docblock( $file ),
				'path' => str_replace( DIRECTORY_SEPARATOR, '/', $file->getFilename() ),
				'root' => $root,
			);

			if ( ! empty( $file->uses ) ) {
				$out['uses'] = $this->exporter->export_uses( $file->uses );
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
				$out['hooks'] = $this->exporter->export_hooks( $file->uses['hooks'] );
			}

			foreach ( $file->getFunctions() as $function ) {
				$func = array(
					'name'      => $function->getShortName(),
					'namespace' => $function->getNamespace(),
					'aliases'   => $function->getNamespaceAliases(),
					'line'      => $function->getLineNumber(),
					'end_line'  => $function->getNode()->getAttribute( 'endLine' ),
					'arguments' => $this->exporter->export_arguments( $function->getArguments() ),
					'doc'       => $this->exporter->export_docblock( $function ),
					'hooks'     => array(),
				);

				if ( ! empty( $function->uses ) ) {
					$func['uses'] = $this->exporter->export_uses( $function->uses );

					if ( ! empty( $function->uses['hooks'] ) ) {
						$func['hooks'] = $this->exporter->export_hooks( $function->uses['hooks'] );
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
					'properties' => $this->exporter->export_properties( $class->getProperties() ),
					'methods'    => $this->exporter->export_methods( $class->getMethods() ),
					'doc'        => $this->exporter->export_docblock( $class ),
				);

				$out['classes'][] = $class_data;
			}

			$output[] = $out;
		}

		return $output;
	}
}
