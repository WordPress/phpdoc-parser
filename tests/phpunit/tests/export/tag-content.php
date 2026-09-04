<?php

/**
 * A test case for splitting a DocBlock tag's content.
 */

namespace WP_Parser\Tests;

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Context;
use phpDocumentor\Reflection\DocBlock\Tag\ParamTag;

use function WP_Parser\export_docblock;
use function WP_Parser\split_docblock_tag_content;

/**
 * Test that a tag's type expression is split from the rest of its content.
 *
 * A type expression may contain whitespace, but only in a few places: inside a
 * callable's parameter list, and directly after a delimiter inside a generic or
 * an array shape. Whitespace anywhere else inside brackets means the brackets
 * aren't a type expression at all, and the content has to be split the way the
 * legacy dependency splits it.
 */
class Export_Tag_Content extends \WP_UnitTestCase {

	/**
	 * Test that a tag's content is split at the end of its type expression.
	 *
	 * @dataProvider data_tag_contents
	 *
	 * @param string   $content  The tag content to split.
	 * @param string[] $expected The expected type expression and remainder.
	 */
	public function test_split_docblock_tag_content( $content, $expected ) {

		$this->assertSame( $expected, split_docblock_tag_content( $content ) );
	}

	/**
	 * Data provider for tag contents.
	 *
	 * @return array[] The tag content, and its expected split.
	 */
	public function data_tag_contents() {

		return array(
			'quoted literal containing a bracket' => array(
				"'a<b' \$x A description > with gt"
				, array( "'a<b'", '$x A description > with gt' )
			),
			'unclosed generic followed by prose' => array(
				'array<int $x The arrow->prop blah'
				, array( 'array<int', '$x The arrow->prop blah' )
			),
			'generic which balances at the last byte' => array(
				'Generator<int A generator of things>'
				, array( 'Generator<int', 'A generator of things>' )
			),
			'callable with a return type' => array(
				'callable(int $a, string $b): bool $cb Desc.'
				, array( 'callable(int $a, string $b): bool', '$cb Desc.' )
			),
			'callable with a return type and nothing after it' => array(
				'callable(string): string'
				, array( 'callable(string): string', '' )
			),
			'callable with an empty parameter list' => array(
				'callable(): bool $cb Empty.'
				, array( 'callable(): bool', '$cb Empty.' )
			),
			'leading group full of prose' => array(
				'(mixed depends on context)'
				, array( '(mixed', 'depends on context)' )
			),
			'leading group followed by a colon' => array(
				'(bool): true on success'
				, array( '(bool):', 'true on success' )
			),
			'grouped union followed by a variable' => array(
				'(int|string)[] $x Grouped.'
				, array( '(int|string)[]', '$x Grouped.' )
			),
			'generic followed by a by-reference variable' => array(
				'array<int, string> &$arr By ref.'
				, array( 'array<int, string>', '&$arr By ref.' )
			),
			'array shape which begins with a variable' => array(
				'$map{int, string} Some desc'
				, array( '$map{int, string}', 'Some desc' )
			),
			'array shape' => array(
				'array{a: int, b: string} $s Shape.'
				, array( 'array{a: int, b: string}', '$s Shape.' )
			),
			'generic with a space after the delimiter' => array(
				'array<int, string> $map A map.'
				, array( 'array<int, string>', '$map A map.' )
			),
			'plain type' => array(
				'string $var A string value.'
				, array( 'string', '$var A string value.' )
			),
		);
	}

	/**
	 * Test that content which isn't valid UTF-8 is split bytewise.
	 */
	public function test_split_docblock_tag_content_of_invalid_utf8() {

		$this->assertSame(
			array( "array<int,\xC2\xA0string>", "\xFF\$var Desc" )
			, split_docblock_tag_content( "array<int,\xC2\xA0string> \xFF\$var Desc" )
		);
	}

	/**
	 * Test that a tag whose content isn't valid UTF-8 is exported as parsed.
	 *
	 * Content which isn't valid UTF-8 is split bytewise, so the type expression
	 * may contain whitespace which only a Unicode-mode match would find. Deciding
	 * whether to re-derive the variable and the description with a Unicode-mode
	 * match leaves the two halves of the export disagreeing: the description is
	 * rewritten from a split which never happened, while the variable isn't.
	 *
	 * The legacy dependency can't parse a DocBlock which isn't valid UTF-8 at all
	 * -- it returns no tags -- so the tag is built directly here.
	 */
	public function test_export_docblock_of_invalid_utf8_content() {

		$context  = new Context( '\Ns' );
		$docblock = new DocBlock( "/**\n * Summary.\n */", $context );
		$tag      = new Invalid_UTF8_Param_Tag( 'param', 'array<int,string> $var Desc', $docblock );

		$docblock->appendTag( $tag );

		$exported = export_docblock( new Docblock_Bearing_Element( $docblock ) );

		$this->assertSame( '$var', $exported['tags'][0]['variable'] );
		$this->assertSame( 'Desc', $exported['tags'][0]['content'] );
	}
}

/**
 * A param tag whose content isn't valid UTF-8.
 *
 * The content is overridden rather than passed to the constructor because the
 * legacy dependency emits a PHP warning while parsing content which isn't valid
 * UTF-8.
 */
class Invalid_UTF8_Param_Tag extends ParamTag {

	/**
	 * @return string
	 */
	public function getContent() {

		return "array<int,\xC2\xA0string> \xFF\$var Desc";
	}
}

/**
 * A stand-in for a reflector, which is all that the exporter needs of one.
 */
class Docblock_Bearing_Element {

	/**
	 * @var DocBlock
	 */
	protected $docblock;

	/**
	 * @param DocBlock $docblock The DocBlock to export.
	 */
	public function __construct( $docblock ) {

		$this->docblock = $docblock;
	}

	/**
	 * @return DocBlock
	 */
	public function getDocBlock() {

		return $this->docblock;
	}
}
