<?php
/**
 * Plugin Name: WP Parser
 * Description: Create a function reference site powered by WordPress
 * Author: Ryan McCue, Paul Gibbs, Andrey "Rarst" Savchenko and Contributors
 * Author URI: https://github.com/rmccue/WP-Parser/graphs/contributors
 * Plugin URI: https://github.com/rmccue/WP-Parser
 * Version:
 */

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

global $wp_parser;
$wp_parser = new WP_Parser\Plugin();
$wp_parser->on_load();

register_activation_hook( __FILE__, array( 'P2P_Storage', 'init' ) );
register_activation_hook( __FILE__, array( 'P2P_Storage', 'install' ) );
register_uninstall_hook( __FILE__, array( 'P2P_Storage', 'uninstall' ) );