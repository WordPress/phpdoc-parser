<?php

/**
 * A test case for expanding docblock type expressions.
 */

namespace WP_Parser\Tests;

use phpDocumentor\Reflection\DocBlock\Context;

use function WP_Parser\expand_docblock_type_expression;

/**
 * Test that type expressions are expanded without losing or inventing information.
 */
class Export_Type_Expressions extends \WP_UnitTestCase {

	/**
	 * Test type expressions expanded within the `\Ns` namespace.
	 *
	 * @dataProvider data_namespaced_type_expressions
	 *
	 * @param string $type     The type expression to expand.
	 * @param string $expected The expected expansion.
	 */
	public function test_namespaced_type_expressions( $type, $expected ) {

		$this->assertSame(
			$expected
			, expand_docblock_type_expression( $type, new Context( '\Ns' ) )
		);
	}

	/**
	 * Data provider for type expressions expanded within the `\Ns` namespace.
	 *
	 * @return array[] The type expression, and its expected expansion.
	 */
	public function data_namespaced_type_expressions() {

		return array(
			'parenthesized union with an array suffix' => array(
				'(int|string)[]'
				, '(int|string)[]'
			),
			'array shape' => array(
				'array{a: int|string}'
				, 'array{a: int|string}'
			),
			'callable signature' => array(
				'callable(int|string): void'
				, 'callable(int|string): void'
			),
			'unbalanced brackets' => array(
				'array<int|string'
				, 'array<int|string'
			),
			'integer range' => array(
				'int<0,max>'
				, 'int<0,max>'
			),
			'integer range with a lower bound keyword' => array(
				'int<min,0>'
				, 'int<min,0>'
			),
			'max keyword' => array(
				'max'
				, 'max'
			),
			'min keyword' => array(
				'min'
				, 'min'
			),
			'class named like the max keyword' => array(
				'Max'
				, '\Ns\Max'
			),
			'class named like the min keyword' => array(
				'Min'
				, '\Ns\Min'
			),
			'class constant' => array(
				'Base::TYPE_FEED'
				, '\Ns\Base::TYPE_FEED'
			),
			'class constant wildcard' => array(
				'Foo::*'
				, '\Ns\Foo::*'
			),
			'class constant wildcard on a keyword' => array(
				'self::LOCATOR_*'
				, 'self::LOCATOR_*'
			),
			'nullable class name' => array(
				'?Foo'
				, '?\Ns\Foo'
			),
			'nullable class name with an array suffix' => array(
				'?Foo[]'
				, '?\Ns\Foo[]'
			),
			'nullable keyword' => array(
				'?string'
				, '?string'
			),
			'nullable never keyword' => array(
				'?never'
				, '?never'
			),
			'generic with a leading integer literal' => array(
				'array<0,string>'
				, 'array<0,string>'
			),
			'generic with a quoted string literal' => array(
				"array<int,'a'>"
				, "array<int,'a'>"
			),
			'iterable generic' => array(
				'iterable<Foo>'
				, 'iterable<\Ns\Foo>'
			),
			'class-string generic' => array(
				'class-string<Foo>'
				, 'class-string<\Ns\Foo>'
			),
			'non-empty-list generic' => array(
				'non-empty-list<int>'
				, 'non-empty-list<int>'
			),
			'never keyword' => array(
				'never'
				, 'never'
			),
			'generic with a space after the delimiter' => array(
				'array<int, string>'
				, 'array<int,string>'
			),
			'repeated array suffixes' => array(
				'Foo[][]'
				, '\Ns\Foo[][]'
			),
		);
	}

	/**
	 * Test type expressions expanded with an aliased namespace.
	 *
	 * @dataProvider data_aliased_type_expressions
	 *
	 * @param string $type     The type expression to expand.
	 * @param string $expected The expected expansion.
	 */
	public function test_aliased_type_expressions( $type, $expected ) {

		$context = new Context( '\Acme', array( 'Bar' => 'Vendor\Bar' ) );

		$this->assertSame( $expected, expand_docblock_type_expression( $type, $context ) );
	}

	/**
	 * Data provider for type expressions expanded with an aliased namespace.
	 *
	 * @return array[] The type expression, and its expected expansion.
	 */
	public function data_aliased_type_expressions() {

		return array(
			'intersection' => array(
				'Bar&Foo'
				, '\Vendor\Bar&\Acme\Foo'
			),
			'intersection inside a generic' => array(
				'list<Bar&Foo>'
				, 'list<\Vendor\Bar&\Acme\Foo>'
			),
			'nullable alias' => array(
				'?Bar'
				, '?\Vendor\Bar'
			),
		);
	}

	/**
	 * Test that a great many array suffixes are expanded without quadratic blowup.
	 */
	public function test_repeated_array_suffixes_do_not_blow_up() {

		$type = 'int' . str_repeat( '[]', 20000 );

		$start    = microtime( true );
		$expanded = expand_docblock_type_expression( $type, new Context( '\Ns' ) );
		$elapsed  = microtime( true ) - $start;

		$this->assertTrue( $type === $expanded, 'The expanded type expression should be unchanged.' );
		$this->assertLessThan(
			5
			, $elapsed
			, 'Expanding repeated array suffixes should not take quadratic time.'
		);
	}

	/**
	 * Test that deeply nested generics are expanded without quadratic blowup.
	 *
	 * Each level of nesting rescans and copies the whole remaining expression, so
	 * the cost grows with the square of the length of the expression rather than
	 * with its length.
	 *
	 * The depth is chosen so that the expansion currently takes about two seconds
	 * while staying under a hundred and thirty megabytes, which is the smallest
	 * memory limit this runs under.
	 */
	public function test_deeply_nested_generics_do_not_blow_up() {

		$depth = 2200;
		$type  = str_repeat( 'array<', $depth ) . 'int' . str_repeat( '>', $depth );

		$start    = microtime( true );
		$expanded = expand_docblock_type_expression( $type, new Context( '\Ns' ) );
		$elapsed  = microtime( true ) - $start;

		$this->assertTrue( $type === $expanded, 'The expanded type expression should be unchanged.' );
		$this->assertLessThan(
			1
			, $elapsed
			, 'Expanding deeply nested generics should not take quadratic time.'
		);
	}
}
