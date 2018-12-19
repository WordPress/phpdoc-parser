<?php namespace WP_Parser;

use Symfony\Component\Finder\Finder;

class Utils {

	public static function get_files( $directory, $exclude_files = array() ) {
		$finder = new Finder();

		try {
			return $finder
				->ignoreDotFiles( true )
				->ignoreVCS( true )
				->exclude( $exclude_files )
				->files()
				->name( '*.php' )
				->in( $directory );
		} catch( \Exception $e ) {
			return new \WP_Error(
				'unexpected_value_exception',
				sprintf( 'Directory [%s] contained a directory we can not recurse into', $directory )
			);
		}
	}
}
