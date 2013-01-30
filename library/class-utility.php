<?php

class Codex_Generator_Utility {

	/**
	 * Pads exploded array to target number of elements with default value.
	 *
	 * @param string $delimiter
	 * @param string $string
	 * @param int $count
	 * @param mixed $default
	 *
	 * @return array
	 */
	static function explode( $delimiter, $string, $count, $default ) {

		$output = array();
		$pieces = substr_count( $string, $delimiter ) + 1;

		if ($pieces < 2)
			$output[] = $string;
		elseif ($pieces >= $count)
			$output = explode( $delimiter, $string, $count );
		else
			$output = explode( $delimiter, $string );

		while ( $count > count( $output ) )
			$output[] = $default;

		return $output;
	}

	/**
	 * Retrieves relative path to file, containing a function.
	 *
	 * @param string $path full local path
	 *
	 * @return string file path
	 */
	static function sanitize_path( $path ) {

		static $abspath, $content, $content_dir, $plugin, $plugin_dir;

		if ( empty( $abspath ) ) {
			$abspath     = self::trim_and_forward_slashes( ABSPATH );
			$content     = self::trim_and_forward_slashes( WP_CONTENT_DIR );
			$content_dir = self::last_dir( $content );
			$plugin      = self::trim_and_forward_slashes( WP_PLUGIN_DIR );
			$plugin_dir  = self::last_dir( $plugin );
		}

		$path = self::trim_and_forward_slashes( $path );

		if ( false !== strpos( $path, $plugin ) ) {
			$prepend = false !== strpos( $path, $content ) ? "{$content_dir}/{$plugin_dir}" : $plugin_dir;
			$path    = $prepend . str_replace( $plugin, '', $path );
		}
		elseif ( false !== strpos( $path, $content ) ) {
			$path = $content_dir . str_replace( $content, '', $path );
		}
		else {
			$path = str_replace( $abspath, '', $path );
		}

		$path = self::trim_and_forward_slashes( $path );

		return $path;
	}

	/**
	 * Trims any slashes and turns rest to forward.
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	static function trim_and_forward_slashes( $path ) {

		$path = trim( $path, '\/' );
		$path = str_replace( '\\', '/', $path );

		return $path;
	}

	/**
	 * Returns last level of path.
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	static function last_dir( $path ) {

		return array_pop( preg_split( '/[\/\\\]/', $path ) );
	}

	/**
	 * Cleans up version, changes MU to 3.0.0
	 *
	 * @param string $version
	 *
	 * @return string
	 */
	static function sanitize_version( $version ) {

		if ( 'MU' == trim( $version ) )
			$version = '3.0.0';

		$version = preg_replace( '/[^\d\.]/', '', $version );
		$version = trim( $version, '.' );

		return $version;
	}

	/**
	 * @param string $compare
	 * 
	 * @return string
	 */
	static function sanitize_compare( $compare ) {

		$valid_compare = self::get_compare();
		$compare       = html_entity_decode( $compare );

		if ( in_array( $compare, $valid_compare ) )
			return $compare;

		return '=';
	}

	/**
	 * @return array
	 */
	static function get_compare() {

		return array( '=', '>', '>=', '<', '<=', '!=' );
	}

	/**
	 * Trims trailing zero on major versions.
	 *
	 * @param string $version
	 * 	
	 * @return string
	 */
	static function trim_version( $version ) {

		if ( strlen( $version ) > 3 && '.0' === substr( $version, - 2 ) )
			$version = substr( $version, 0, 3 );

		return $version;
	}

	/**
	 * Adjust type names to forms, supported by Codex.
	 *
	 * @param mixed $type
	 * @param string $context
	 *
	 * @return string
	 */
	static function type_to_string( $type, $context = '' ) {

		if ( 'wiki' == $context )
			$type = str_replace( '|', '&#124;', $type );

		if ( false === strpos( $type, 'boolean' ) )
			$type = str_replace( 'bool', 'boolean', $type );

		return $type;
	}

	/**
	 * Turns mixed type values into string representation.
	 *
	 * @param mixed $value
	 *
	 * @return string
	 */
	static function value_to_string( $value ) {

		if ( is_null( $value ) )
			$value = 'null';

		elseif ( is_bool( $value ) )
			$value = $value ? 'true' : 'false';

		elseif ( is_string( $value ) && empty( $value ) )
			$value = "''";

		elseif ( is_array( $value ) )
			if ( empty( $value ) )
				$value = 'array()';
			else
				$value = "array( '" . implode( "','", $value ) . "')";

		return $value;
	}

	/**
	 * Get link markup for function reference.
	 *
	 * @param string $function
	 * @param string $anchor
	 * 
	 * @return string
	 */
	static function get_codex_link( $function, $anchor = '' ) {

		$link = esc_html( 'codex.wordpress.org/Function_Reference/' . $function );
		$href = esc_url( $link );

		if( $anchor )
			$link = $anchor;

		$link = "<a href='{$href}'>{$link}</a>";

		return $link;
	}
}