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
}
