<?php

namespace WP_Parser;

use phpDocumentor\Reflection\BaseReflector;
use phpDocumentor\Reflection\ClassReflector;

/**
 * A reflection of a method call expression.
 */
class Method_Call_Reflector extends BaseReflector {

	/**
	 * The class that this method was called in, if it was called in a class.
	 *
	 * @var ClassReflector|false
	 */
	protected $called_in_class = false;

	/**
	 * Returns the name for this Reflector instance.
	 *
	 * @return string[] Index 0 is the calling instance, 1 is the method name.
	 */
	public function getName() {

		if ( 'Expr_New' === $this->node->getType() ) {
			$name = '__construct';
			$caller = $this->node->class;
		} else {
			$name = $this->getShortName();
			$caller = $this->node->var;
		}

		if ( $caller instanceof \PHPParser_Node_Expr ) {
			$printer = new Pretty_Printer;
			$caller = $printer->prettyPrintExpr( $caller );
		} elseif ( $caller instanceof \PHPParser_Node_Name_FullyQualified ) {
			$caller = '\\' . $caller->toString();
		} elseif ( $caller instanceof \PHPParser_Node_Name ) {
			$caller = $caller->toString();
		}

		$caller = $this->_resolveName( $caller );

		// If the caller is a function, convert it to the function name
		if ( is_a( $caller, 'PHPParser_Node_Expr_FuncCall' ) ) {

			// Add parentheses to signify this is a function call
			/** @var \PHPParser_Node_Expr_FuncCall $caller */
			$caller = implode( '\\', $caller->name->parts ) . '()';
		}

		$class_mapping = $this->_getClassMapping();
		if ( array_key_exists( $caller, $class_mapping ) ) {
			$caller = $class_mapping[ $caller ];
		}

		return array( $caller, $name );
	}

	/**
	 * Set the class that this method was called within.
	 *
	 * @param ClassReflector $class
	 */
	public function set_class( ClassReflector $class ) {

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

	/**
	 * Resolve a class name from self/parent.
	 *
	 * @param string $class The class name.
	 *
	 * @return string The resolved class name.
	 */
	protected function _resolveName( $class ) {

		if ( ! $this->called_in_class ) {
			return $class;
		}


		switch ( $class ) {
			case '$this':
			case 'self':
				$namespace = (string) $this->called_in_class->getNamespace();
				$namespace = ( 'global' !== $namespace ) ? $namespace . '\\' : '';
				$class = '\\' . $namespace . $this->called_in_class->getShortName();
				break;
			case 'parent':
				$class = '\\' . $this->called_in_class->getNode()->extends->toString();
				break;
		}

		return $class;
	}
}
