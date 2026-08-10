<?php

use \Global_Alias_Source as Global_Alias;

/**
 * Documents a global type.
 *
 * Calls {@see Global_Doc_Function()} while preserving \xC0 as documentation.
 *
 * @param Global_Doc_Type $value Value.
 */
function documented_global_type( \Global_Parameter $value, $enabled = true, $nothing = null, $mode = GLOBAL_MODE, $escape = '\xC0', $namespaced = \Vendor\GLOBAL_MODE ) {}

class Global_Child extends \Global_Parent implements \Global_Interface {}

\Global_Class::global_method();

const GLOBAL_CONST = GLOBAL_VALUE;

define( 'GLOBAL_DEFINED_CONST', global_default( GLOBAL_VALUE ) );
define( 'NAMESPACED_DEFINED_CONST', \Vendor\global_default( \Vendor\GLOBAL_VALUE ) );

class Global_Defaults {
	public $enabled = false;
	public $mode = GLOBAL_MODE;

	public function create() {
		return ( new \Global_Class() )->global_method();
	}

	public function create_namespaced() {
		return ( new \Vendor\Global_Class() )->global_method();
	}
}

/**
 * Documents inline references.
 *
 * Calls {@see \Global_Doc_Function()} and {@see \Vendor\Thing::m()} in prose.
 *
 * Spells `{@see \Global_Inline::method()}` in an inline code span.
 *
 * ```php
 * // Renders {@see \Global_Widget::render()}.
 * $widget->render();
 * ```
 */
function documented_inline_references() {}
