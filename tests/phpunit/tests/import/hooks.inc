<?php

/**
 * Hooks whose names sanitize to the same slug, or are stored altered.
 *
 * @package Something
 */

/**
 * Activates a plugin.
 *
 * @since 1.0.0
 *
 * @param string $plugin Path to the plugin file relative to the plugins directory.
 */
function wp_parser_activate_plugin( $plugin ) {

	/**
	 * Fires before a plugin is activated.
	 *
	 * @since 2.9.0
	 *
	 * @param string $plugin Path to the plugin file relative to the plugins directory.
	 */
	do_action( 'activate_plugin', $plugin );

	/**
	 * Fires as a specific plugin is being activated.
	 *
	 * @since 2.0.0
	 */
	do_action( "activate_{$plugin}" );

	/**
	 * Fires for the current screen, whose name kses stores with `&gt;`.
	 *
	 * @since 3.0.0
	 */
	do_action( "manage_{$screen->id}_columns" );
}

// EOF
