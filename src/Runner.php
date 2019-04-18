<?php namespace WP_Parser;

use phpDocumentor\Reflection\Exception\UnparsableFile;
use phpDocumentor\Reflection\Exception\UnreadableFile;
use Symfony\Component\Finder\Finder;
use WP_CLI;
use WP_Parser\Reflectors\File_Reflector;

/**
 * Class Runner
 *
 * @package WP_Parser
 */
class Runner {
	// CLI config + config file
	protected $exclude_directories = [ 'vendor', 'vendor_prefixed', 'node_modules', 'tests', 'build' ];

	private $exporter;
	private $ignore_files;

	/**
	 * Runner constructor.
	 *
	 * @param array $ignore_files Files to ignore.
	 */
	public function __construct( $ignore_files = [] ) {
		$this->ignore_files = $ignore_files;
		$this->exporter = new Exporter();
	}

	/**
	 * Collects all applicable files for later parsing.
	 *
	 * @param string $directory The directory to get the files from.
	 *
	 * @param bool   $skip_unreadable_dirs
	 *
	 * @return array|\WP_Error The parsable files. Returns an error if a directory can't be recursed into.
	 */
	public function get_wp_files( $directory, $skip_unreadable_dirs = true ) {
		$finder = new Finder();
		$finder
			->files()
			->in( $directory )
			->exclude( $this->exclude_directories )
			->name( '*.php' );

		if ( $skip_unreadable_dirs ) {
			$finder = $finder->ignoreUnreadableDirs();
		}

		if ( ! $finder->hasResults() ) {
			return [];
		}

		return iterator_to_array( $finder, false );
	}

	/**
	 * Parses the passed files and attempts to extract the proper docblocks from the files.
	 *
	 * @param array  $files The files to extract the docblocks from.
	 * @param string $root	The root path where the parsing was initially started in.
	 *
	 * @return array The extracted docblocks.
	 *
	 * @throws UnparsableFile Thrown if the file can't be parsed.
	 * @throws UnreadableFile Thrown if the file can't be read.
	 */
	public function parse_files( $files, $root ) {
		$output = [];

		/** @var Symfony\Component\Finder\SplFileInfo $file */
		foreach ( $files as $file ) {
			$file_reflector = new File_Reflector( $file->getRealPath() );

			$file_reflector->setFilename( $file->getRelativePath() . DIRECTORY_SEPARATOR . $file->getFilename() );
			$file_reflector->process();

			$out = [
				'file' => $this->exporter->export_docblock( $file_reflector ),
				'path' => str_replace( DIRECTORY_SEPARATOR, '/', $file_reflector->getFilename() ),
				'root' => $root,
			];

			if ( ! empty( $file_reflector->uses ) ) {
				$out['uses'] = $this->exporter->export_uses( $file_reflector->uses );
			}

			foreach ( $file_reflector->getIncludes() as $include ) {
				$out['includes'][] = [
					'name' => $include->getName(),
					'line' => $include->getLineNumber(),
					'type' => $include->getType(),
				];
			}

			foreach ( $file_reflector->getConstants() as $constant ) {
				$out['constants'][] = [
					'name'  => $constant->getShortName(),
					'line'  => $constant->getLineNumber(),
					'value' => $constant->getValue(),
				];
			}

			if ( ! empty( $file_reflector->uses['hooks'] ) ) {
				$out['hooks'] = $this->exporter->export_hooks( $file_reflector->uses['hooks'] );
			}

			foreach ( $file_reflector->getFunctions() as $function ) {
				$func = [
					'name'      => $function->getShortName(),
					'namespace' => $function->getNamespace(),
					'aliases'   => $function->getNamespaceAliases(),
					'line'      => $function->getLineNumber(),
					'end_line'  => $function->getNode()->getAttribute( 'endLine' ),
					'arguments' => $this->exporter->export_arguments( $function->getArguments() ),
					'doc'       => $this->exporter->export_docblock( $function ),
					'hooks'     => [],
				];

				if ( ! empty( $function->uses ) ) {
					$func['uses'] = $this->exporter->export_uses( $function->uses );

					if ( ! empty( $function->uses['hooks'] ) ) {
						$func['hooks'] = $this->exporter->export_hooks( $function->uses['hooks'] );
					}
				}

				$out['functions'][] = $func;
			}

			foreach ( $file_reflector->getClasses() as $class ) {
				$class_data = [
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
				];

				$out['classes'][] = $class_data;
			}

			$output[] = $out;
		}

		return $output;
	}
}
