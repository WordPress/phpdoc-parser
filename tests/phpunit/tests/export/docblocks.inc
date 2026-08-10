<?php

/**
 * This is the file-level docblock summary.
 *
 * This is the file-level docblock description, which may span multiple lines. In
 * fact, this one does. It spans more than two full lines, continuing on to the
 * third line.
 *
 * ```setup-blueprint file-greeting
 * {
 *   "steps": [
 *     {
 *       "step": "writeFile",
 *       "path": "/wordpress/wp-content/mu-plugins/file-greeting.php",
 *       "data": "<?php\nfunction docs_file_greeting() {\n\treturn 'Hello from the file setup';\n}\n"
 *     }
 *   ]
 * }
 * ```
 *
 * @since 1.5.0
 */

use WordPress\AiClient\Messages\DTO\MessagePart;

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
 * Tests a nested union type inside a generic.
 *
 * @param MessagePart|list<string|MessagePart|array>|MessagePart[] $prompt A prompt.
 */
function test_nested_union_type( $prompt ) {
}

/**
 * Tests a parenthesized union with an array suffix.
 *
 * @param (int|MessagePart)[] $x Grouped.
 */
function test_grouped_union_type( $x ) {
}

/**
 * Tests a generic type which contains a space after the delimiter.
 *
 * @param array<int, string> $map A map.
 */
function test_generic_type_with_space( $map ) {
}

/**
 * Tests a type separated from the variable by a non-breaking space.
 *
 * @param string $nb NBSP separated.
 */
function test_nbsp_separated_type( $nb ) {
}

/**
 * Tests an iterable generic type.
 *
 * @param iterable<MessagePart> $it Iterable.
 */
function test_iterable_generic_type( $it ) {
}

/**
 * Tests an integer range return type.
 *
 * @return int<0,max> Count.
 */
function test_int_range_return_type() {
}

/**
 * Tests an intersection type.
 *
 * @param MessagePart&Countable $both Intersect.
 */
function test_intersection_type( $both ) {
}

/**
 * Tests a quoted string literal type which contains a bracket.
 *
 * @param 'a<b' $x A description > with gt
 */
function test_quoted_literal_type( $x ) {
}

/**
 * Tests an unclosed generic type followed by prose.
 *
 * @param array<int $x The arrow->prop blah
 */
function test_unclosed_generic_type( $x ) {
}

/**
 * Tests a generic which is missing a comma and balances at the last byte.
 *
 * @return Generator<int A generator of things>
 */
function test_unbalanced_generic_return_type() {
}

/**
 * Tests a callable type which declares a return type.
 *
 * @param callable(int $a, string $b): bool $cb Desc.
 */
function test_callable_return_type( $cb ) {
}

/**
 * Tests a parameter which is passed by reference.
 *
 * @param array<int, string> &$arr By ref.
 */
function test_by_reference_param( &$arr ) {
}

/**
 * Tests a return type which is one leading paren group full of prose.
 *
 * @return (mixed depends on context)
 */
function test_leading_group_prose_return_type() {
}

/**
 * Tests a leading paren group which is followed by a colon.
 *
 * @return (bool): true on success
 */
function test_leading_group_colon_return_type() {
}

/**
 * Tests a callable type with an empty parameter list.
 *
 * @param callable(): bool $cb Empty.
 */
function test_empty_callable_param( $cb ) {
}

/**
 * Tests an untyped parameter which is passed by reference.
 *
 * @param &$arr By ref desc.
 */
function test_untyped_by_reference_param( &$arr ) {
}

/**
 * Tests an untyped variadic parameter which is passed by reference.
 *
 * @param &...$rest The rest.
 */
function test_untyped_variadic_by_reference_param( &...$rest ) {
}

/**
 * This is a class docblock.
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
	 * ```php interactive setup-blueprint=file-greeting
	 * <?php
	 * // This property snippet line ends in a period.
	 * @unlink( '/tmp/phpdoc-parser-property' );
	 * require '/wordpress/wp-load.php';
	 * echo docs_file_greeting();
	 * ```
	 *
	 * ```expected-output
	 * Hello from the file setup
	 * ```
	 *
	 * @since 3.0.0
	 *
	 * @var string
	 */
	public $a_string;

	/**
	 * This is a callable property.
	 *
	 * @var callable(string): string
	 */
	public $a_callback;

	/**
	 * This is a property whose type expression begins with a variable.
	 *
	 * @var $map{int, string} Some desc
	 */
	public $a_shape;

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
	 * This is a method docblock with a code snippet.
	 *
	 * Use this example:
	 *
	 * ```setup-blueprint
	 * {
	 *   "steps": [
	 *     {
	 *       "step": "writeFile",
	 *       "path": "/wordpress/wp-content/mu-plugins/docs-fixture.php",
	 *       "data": "<?php\nfunction docs_fixture_greeting() {\n\treturn 'Hello from a method';\n}\n"
	 *     }
	 *   ]
	 * }
	 * ```
	 *
	 * ```php interactive
	 * <?php
	 * require '/wordpress/wp-load.php';
	 * echo docs_fixture_greeting();
	 * ```
	 *
	 * ```expected-output
	 * Hello from a method
	 * ```
	 *
	 * @since 6.9.0
	 */
	public function test_method_with_code_snippet() {
	}

	/**
	 * This is a method docblock with a reused setup Blueprint.
	 *
	 * ```setup-blueprint shared-greeting
	 * {
	 *   "steps": [
	 *     {
	 *       "step": "writeFile",
	 *       "path": "/wordpress/wp-content/mu-plugins/shared-greeting.php",
	 *       "data": "<?php\nfunction docs_shared_greeting( $name ) {\n\treturn \"Hello, $name\";\n}\n"
	 *     }
	 *   ]
	 * }
	 * ```
	 *
	 * ```php interactive setup-blueprint=shared-greeting
	 * <?php
	 * require '/wordpress/wp-load.php';
	 * echo docs_shared_greeting( 'first' );
	 * ```
	 *
	 * ```expected-output
	 * Hello, first
	 * ```
	 *
	 * ```php interactive setup-blueprint=shared-greeting
	 * <?php
	 * require '/wordpress/wp-load.php';
	 * echo docs_shared_greeting( 'second' );
	 * ```
	 *
	 * ```expected-output
	 * Hello, second
	 * ```
	 *
	 * @since 6.9.0
	 */
	public function test_method_with_reused_setup_blueprint() {
	}

	/**
	 * This is a method docblock with a file-level setup Blueprint.
	 *
	 * ```php interactive setup-blueprint=file-greeting
	 * <?php
	 * require '/wordpress/wp-load.php';
	 * echo docs_file_greeting();
	 * ```
	 *
	 * ```expected-output
	 * Hello from the file setup
	 * ```
	 *
	 * @since 6.9.0
	 */
	public function test_method_with_file_setup_blueprint() {
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
 * ```php interactive setup-blueprint=file-greeting
 * <?php
 * require '/wordpress/wp-load.php';
 * echo docs_file_greeting();
 * ```
 *
 * ```expected-output
 * Hello from the file setup
 * ```
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
