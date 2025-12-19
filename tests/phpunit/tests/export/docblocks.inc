<?php

/**
 * This is the file-level docblock summary.
 *
 * This is the file-level docblock description, which may span multiple lines. In
 * fact, this one does. It spans more than two full lines, continuing on to the
 * third line.
 *
 * @since 1.5.0
 */

/**
 * This is a function docblock.
 *
 * This function is just a test, but we've added this description anyway.
 *
 * @since 2.6.0
 *
 * @param string $var A string value.
 * @param int    $num A number.
 *
 * @return bool Whether the function was called correctly.
 */
function test_func( $var, $num ) {

	// In real life, there *might* be more stuff in here.
	return true;
}

/**
 * This is a class docblock, the summary of the class is
 * spread over multiple lines.
 *
 * This is the more wordy description: This is a comment with two *'s at the start,
 * which means that it is a doc comment. Docblock comments are comment blocks used
 * to document code. This one documents the Test_Class class.
 *
 * @since 3.5.2
 */
class Test_Class {

	/**
	 * This is a docblock for a class property.
	 *
	 * @since 3.0.0
	 *
	 * @var string
	 */
	public $a_string;

	/**
	 * This is a method docblock.
	 *
	 * @since 4.5.0
	 *
	 * @param mixed $var A parameter.
	 * @param array $arr Another parameter.
	 *
	 * @return mixed The first param.
	 */
	public function test_method( $var, $arr ) {
		return $var;
	}

	/**
	 * A test method that contains various @see.
	 *
	 * @see self::test_method_typed_hash()
	 * @see https://wordpressfoundation.org/ The WordPress Foundation.
	 */
	public function test_method_see() {
	}

	/**
	 * This is a method that has a param with multiple types within.
	 *
	 * @param array $hashed_array {
	 *    The parameters for this function.
	 *
	 *    @type int $time The current epoch.
	 *    @type ?string $nullable_string A nullable string.
	 *    @type string|array List of items:
	 *                       - 'item1'
	 *                       - 'item2'
	 *                       Default is 'item1'.
	 * }
	 * @param WP_Post|WP_User $post_or_user A Post or User.
	 * @param ( WP_Post | null ) $nullable_post A Nullable post.
	 * @param ?string $nullable_string A nullable string.
	 * 
	 * @return array An empty array.
	 */
	public function test_method_typed_hash( array $hashed_array, $post_or_user, ?WP_Post $nullable_post ): array {
		return [];
	}

}

/**
 * A filter.
 */
$var = apply_filters( 'test_filter', $var );

/**
 * A reference array filter.
 */
$var = apply_filters_ref_array( 'test_ref_array_filter', array( &$var ) );

/**
 * A test action.
 *
 * @since 3.7.0
 *
 * @param WP_Post $post Post object.
 */
do_action( 'test_action', $post );

do_action( 'undocumented_hook' );

/**
 * A reference array action.
 */
do_action_ref_array( 'test_ref_array_action', array( &$var ) );

/**
 * This method has all kinds of _"markdown formatting"_, many of which are not supported.
 *
 * Example `code`:
 *
 *     foo();
 *     bar();
 *
 * Lists:
 * - Item 1
 * - Item 2
 *
 * This is a quote:
 *
 * > Quoted Content.
 * > On multiple lines.
 *
 * ## Inline Formatting includes Headings.
 *
 * There's also _italics_ and **bold**.
 */
function test_markdown_in_description() {

}