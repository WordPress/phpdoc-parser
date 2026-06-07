<?php

/**
 * This is the file-level docblock summary.
 *
 * This is the file-level docblock description, which may span multiple lines. In
 * fact, this one does. It spans more than two full lines, continuing on to the
 * third line.
 *
 * ```setupblueprint file-greeting
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
	 * This is a method docblock with a code snippet.
	 *
	 * Use this example:
	 *
	 * ```blueprint
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
	 * ```php
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
	 * ```setupblueprint shared-greeting
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
	 * ```php blueprint=shared-greeting
	 * <?php
	 * require '/wordpress/wp-load.php';
	 * echo docs_shared_greeting( 'first' );
	 * ```
	 *
	 * ```expected-output
	 * Hello, first
	 * ```
	 *
	 * ```php setup-blueprint=shared-greeting
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
	 * ```php blueprint=file-greeting
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
