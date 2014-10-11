<?php

/**
 * A reflection class for a method call, either static or non-static
 */

namespace WP_Parser;

use phpDocumentor\Reflection\BaseReflector;

/**
 * A reflection of a function call expression.
 */
class Method_Call_Reflector extends BaseReflector {

	/**
	 * Returns the name for this Reflector instance.
	 *
	 * @return string
	 */
	public function getName() {
		if ( isset( $this->node->namespacedName ) ) {
			return '\\' . implode( '\\', $this->node->namespacedName->parts );
		}

		$shortName = $this->getShortName();

		if ( ! is_a( $shortName, 'PHPParser_Node_Name' ) ) {

			/** @var \PHPParser_Node_Expr_ArrayDimFetch $shortName */
			if ( is_a( $shortName, 'PHPParser_Node_Expr_ArrayDimFetch' ) ) {
				$var = $shortName->var->name;
				$dim = $shortName->dim->name->parts[0];

				return "\${$var}[{$dim}]";
			}

			/** @var \PHPParser_Node_Expr_Variable $shortName */
			if ( is_a( $shortName, 'PHPParser_Node_Expr_Variable' ) ) {
				return $shortName->name;
			}
		}

		return (string) $shortName;
	}

	/**
	 * Returns the name of the class this method is called on.
	 *
	 * @return string
	 */
	public function getCalledOn() {
		if ( is_a( $this->node, 'PHPParser_Node_Expr_StaticCall' ) ) {
			$calledOn = $this->node->class->parts[0];
		} else if ( is_a( $this->node, 'PHPParser_Node_Expr_MethodCall' ) ) {
			$calledOn = $this->node->var;

			// This is actually a variable name
			$calledOn = $calledOn->name;
		}

		return $calledOn;
	}

	/**
	 * Returns the class this method exists on if it can be determined
	 *
	 * @return string The class this method exists on
	 */
	public function getClass() {
		$called_on = $this->getCalledOn();

		if ( $this->isStatic() ) {
			if ( 'self' === $called_on ) {
				$class = $this->node->getAttribute( 'containingClass' );
			} else {
				$class = $called_on;
			}
		} else {
			$class_mapping = $this->_getClassMapping();

			if ( 'this' === $called_on ) {
				$class = $this->node->getAttribute( 'containingClass' );
			} else if ( array_key_exists( $called_on, $class_mapping ) ) {
				$class = $class_mapping[ $called_on ];
			} else {
				$class = '';
			}
		}

		return $class;
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

		$class_mapping = $wp_globals;

		return $class_mapping;
	}

	/**
	 * Returns whether or not this method call was static
	 *
	 * @return boolean Whether or not this method call is a static call
	 */
	public function isStatic() {
		return is_a( $this->node, 'PHPParser_Node_Expr_StaticCall' );
	}
}
