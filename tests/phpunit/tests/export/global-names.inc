<?php

use \Global_Alias_Source as Global_Alias;

/**
 * Documents a global type.
 *
 * @param Global_Doc_Type $value Value.
 */
function documented_global_type( \Global_Parameter $value ) {}

class Global_Child extends \Global_Parent implements \Global_Interface {}

\Global_Class::global_method();
