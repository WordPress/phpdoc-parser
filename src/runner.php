<?php namespace WP_Parser;

use WP_CLI;

/**
 * Class Runner
 *
 * @package WP_Parser
 */
class Runner {
	private $exporter;
	private $plugin_data;

	/**
	 * Runner constructor.
	 *
	 * @param array $plugin_data The plugin data to use.
	 */
	public function __construct( $plugin_data = array() ) {
		$this->exporter = new Exporter();
		$this->plugin_data = $plugin_data;
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

		$output = array();

		foreach ( $files as $filename ) {
			$filename = $filename->getPathname();
			$file 	  = new File_Reflector( $filename );
			$path 	  = ltrim( substr( $filename, strlen( $root ) ), DIRECTORY_SEPARATOR );

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

			if ( ! empty( $this->plugin_data ) ) {
				$out['plugin'] = $this->plugin_data['Name'];
			}

			foreach ( $file->getIncludes() as $include ) {
				$include_data = array(
					'name' => $include->getName(),
					'line' => $include->getLineNumber(),
					'type' => $include->getType(),
				);

				if ( ! empty( $this->plugin_data ) ) {
					$include_data['plugin'] = $this->plugin_data['Name'];
				}

				$out['includes'][] = $include_data;
			}

			foreach ( $file->getConstants() as $constant ) {
				$constant_data = array(
					'name'  => $constant->getShortName(),
					'line'  => $constant->getLineNumber(),
					'value' => $constant->getValue(),
				);

				if ( ! empty( $this->plugin_data ) ) {
					$constant_data['plugin'] = $this->plugin_data['Name'];
				}

				$out['constants'][] = $constant_data;
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

				if ( ! empty( $this->plugin_data ) ) {
					$func['plugin'] = $this->plugin_data['Name'];
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

				if ( ! empty( $this->plugin_data ) ) {
					$class_data['plugin'] = $this->plugin_data['Name'];
				}

				$out['classes'][] = $class_data;
			}

			$output[] = $out;
		}

		return $output;
	}
}
