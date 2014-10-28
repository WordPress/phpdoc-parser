<?php

/**
 * A reflection class for a method call.
 */

namespace WP_Parser;

use phpDocumentor\Reflection\BaseReflector;

/**
 * A reflection of a method call expression.
 */
class Method_Call_Reflector extends BaseReflector {

	/**
	 * The class that this method was called in, if it was called in a class.
	 *
	 * @var \phpDocumentor\Reflection\ClassReflector|false
	 */
	protected $called_in_class = false;

	/**
	 * Returns the name for this Reflector instance.
	 *
	 * @return string[] Index 0 is the calling instance, 1 is the method name.
	 */
	public function getName() {
		$name = $this->getShortName();

		$printer = new Pretty_Printer;
		$caller = $printer->prettyPrintExpr( $this->node->var );

		if ( $this->called_in_class && '$this' === $caller ) {
			$caller = $this->called_in_class->getShortName();
		} else {

			// If the caller is a function, convert it to the function name
			if ( is_a( $caller, 'PHPParser_Node_Expr_FuncCall' ) ) {

				// Add parentheses to signify this is a function call
				$caller = $caller->name->parts[0] . '()';
			}

			$class_mapping = $this->_getClassMapping();
			if ( array_key_exists( $caller, $class_mapping ) ) {
				$caller = $class_mapping[ $caller ];
			}
		}

		return array( $caller, $name );
	}

	/**
	 * Set the class that this method was called within.
	 *
	 * @param \phpDocumentor\Reflection\ClassReflector $class
	 */
	public function set_class( \phpDocumentor\Reflection\ClassReflector $class ) {

		$this->called_in_class = $class;
	}

	/**
	 * Returns whether or not this method call is a static call
	 *
	 * @return bool Whether or not this method call is a static call
	 */
	public function isStatic() {
		return false;
	}

	/**
	 * Returns a mapping from variable names to a class name, leverages globals for most used classes
	 *
	 * @return array Class mapping to map variable names to classes
	 */
	protected function _getClassMapping() {

		// List of global use generated using following command:
		// ack "global \\\$[^;]+;" --no-filename | tr -d '\t' | sort | uniq | sed "s/global //g" | sed "s/, /,/g" | tr , '\n' | sed "s/;//g" | sort | uniq | sed "s/\\\$//g" | sed "s/[^ ][^ ]*/'&' => ''/g"
		// There is probably an easier way, there are currently no globals that are classes starting with an underscore
		$wp_globals = array(
			'authordata' => 'WP_User',
			'custom_background' => 'Custom_Background',
			'custom_image_header' => 'Custom_Image_Header',
			'phpmailer' => 'PHPMailer',
			'post' => 'WP_Post',
			'userdata' => 'WP_User', // This can also be stdClass, but you can't call methods on an stdClass
			'wp' => 'WP',
			'wp_admin_bar' => 'WP_Admin_Bar',
			'wp_customize' => 'WP_Customize_Manager',
			'wp_embed' => 'WP_Embed',
			'wp_filesystem' => 'WP_Filesystem',
			'wp_hasher' => 'PasswordHash', // This can be overridden by plugins, for core assume this is ours
			'wp_json' => 'Services_JSON',
			'wp_list_table' => 'WP_List_Table', // This one differs because there are a lot of different List Tables, assume they all only overwrite existing functions on WP_List_Table
			'wp_locale' => 'WP_Locale',
			'wp_object_cache' => 'WP_Object_Cache',
			'wp_query' => 'WP_Query',
			'wp_rewrite' => 'WP_Rewrite',
			'wp_roles' => 'WP_Roles',
			'wp_scripts' => 'WP_Scripts',
			'wp_styles' => 'WP_Styles',
			'wp_the_query' => 'WP_Query',
			'wp_widget_factory' => 'WP_Widget_Factory',
			'wp_xmlrpc_server' => 'wp_xmlrpc_server', // This can be overridden by plugins, for core assume this is ours
			'wpdb' => 'wpdb',
		);

		$wp_functions = array(
			'get_current_screen()' => 'WP_Screen',
			'_get_list_table()' => 'WP_List_Table', // This one differs because there are a lot of different List Tables, assume they all only overwrite existing functions on WP_List_Table
			'wp_get_theme()' => 'WP_Theme',
		);

		$class_mapping = array_merge( $wp_globals, $wp_functions );

		return $class_mapping;
	}

}