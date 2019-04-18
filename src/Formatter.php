<?php namespace WP_Parser;

use Parsedown;

/**
 * Class Formatter
 *
 * @package WP_Parser
 */
class Formatter {

	/**
	 * Fixes the newlines in the passed text to ensure that there's a uniform output.
	 *
	 * @param string $text The text to fix.
	 *
	 * @return string The text with fixed newlines.
	 */
	public static function fix_newlines( $text ) {
		// Non-naturally occurring string to use as temporary replacement.
		$replacement_string = '{{{{{}}}}}';

		// Replace newline characters within 'code' and 'pre' tags with replacement string.
		$text = preg_replace_callback(
			"/(?<=<pre><code>)(.+)(?=<\/code><\/pre>)/s",
			function ( $matches ) use ( $replacement_string ) {
				return preg_replace( '/[\n\r]/', $replacement_string, $matches[1] );
			},
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
	 * Formats the docblock's description.
	 *
	 * @param string $description The description to format.
	 *
	 * @return string The formatted description.
	 */
	public static function format_description( $description ) {
		if ( class_exists( 'Parsedown' ) ) {
			$parsedown   = Parsedown::instance();
			$description = $parsedown->line( $description );
		}

		return $description;
	}
}
