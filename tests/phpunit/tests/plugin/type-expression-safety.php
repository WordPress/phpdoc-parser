<?php

/**
 * A test case for recognizing a type expression which is safe to display as written.
 */

namespace WP_Parser\Tests;

use WP_Parser\Plugin;

/**
 * Test which values are recognized as type expressions safe to display as written.
 *
 * The content filters are written for prose and destroy a type expression, so a
 * value which is one is displayed as written instead. Everything which isn't one
 * has to keep going through those filters, since nothing else stands between raw
 * phpDoc and the page.
 */
class Plugin_Type_Expression_Safety extends \WP_UnitTestCase {

	/**
	 * Test which values are recognized as type expressions.
	 *
	 * @dataProvider data_values
	 *
	 * @param string $value    The value to check.
	 * @param bool   $expected Whether the value is a type expression.
	 */
	public function test_is_type_expression_safe( $value, $expected ) {

		$plugin = new Type_Expression_Safety_Plugin();

		$this->assertSame( $expected, $plugin->is_safe( $value ) );
	}

	/**
	 * Data provider for values to check.
	 *
	 * @return array[] The value, and whether it is a type expression.
	 */
	public function data_values() {

		return array(
			'plain type' => array( 'string', true ),
			'fully qualified class name' => array(
				'\WordPress\AiClient\Messages\DTO\MessagePart'
				, true
			),
			'generic with a nested union' => array( 'list<string|\WP_Post>', true ),
			'generic with a space after the delimiter' => array( 'array<int, string>', true ),
			'generic with two spaces after the delimiter' => array( 'array<int,  string>', true ),
			'generic of the object keyword' => array( 'array<object>', true ),
			'array shape' => array( 'array{a: int, b: string}', true ),
			'grouped union with an array suffix' => array( '(int|string)[]', true ),
			'quoted literal containing a bracket' => array( "'a<b'", true ),
			'callable with a return type' => array(
				'callable(int $a, string $b): bool'
				, true
			),
			'callable with a namespaced parameter' => array(
				'callable(\WP_Post $p): void'
				, true
			),
			'callable with an empty parameter list' => array( 'callable(): bool', true ),

			// Everything below is prose, markup, or a fragment of a type expression.
			'prose' => array( 'Some prose here', false ),
			'group full of prose' => array( '(mixed depends on context)', false ),
			'generic full of prose' => array( 'Generator<int A generator of things>', false ),
			'unclosed generic' => array( 'array<int', false ),
			'closing tag' => array( '</b>', false ),
			'attribute' => array( '<img src=x>', false ),
			'element in front of text' => array( '<b>hello', false ),

			/*
			 * An element whose content isn't parsed as markup swallows everything
			 * after it even with no attributes and no closing tag.
			 */
			'script element' => array( 'array<script>', false ),
			'style element' => array( 'array<style>', false ),
			'iframe element' => array( 'array<iframe>', false ),
			'xmp element' => array( 'array<xmp>', false ),
			'textarea element' => array( 'array<textarea>', false ),
			'title element' => array( 'array<title>', false ),
			'svg element' => array( 'array<svg>', false ),
			'math element' => array( 'array<math>', false ),
			'template element' => array( 'array<template>', false ),
			'plaintext element' => array( 'array<plaintext>', false ),
			'noembed element' => array( 'array<noembed>', false ),
			'noframes element' => array( 'array<noframes>', false ),
			'noscript element' => array( 'array<noscript>', false ),
			'listing element' => array( 'array<listing>', false ),
			'select element' => array( 'array<select>', false ),
			'element in mixed case' => array( 'array<PlainText>', false ),
		);
	}
}

/**
 * A plugin which exposes the check for a type expression.
 */
class Type_Expression_Safety_Plugin extends Plugin {

	/**
	 * @param string $value Value to check.
	 *
	 * @return bool
	 */
	public function is_safe( $value ) {

		return $this->is_type_expression_safe( $value );
	}
}
