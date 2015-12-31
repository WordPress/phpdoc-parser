<?php
/**
 * Plugin Name: WP Parser
 * Description: Create a function reference site powered by WordPress
 * Author: Ryan McCue, Paul Gibbs, Andrey "Rarst" Savchenko and Contributors
 * Author URI: https://github.com/WordPress/phpdoc-parser/graphs/contributors
 * Plugin URI: https://github.com/WordPress/phpdoc-parser
 * Version:
 * Text Domain: wp-parser
 */

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

global $wp_parser;
$wp_parser = new WP_Parser\Plugin();
$wp_parser->on_load();

register_activation_hook( __FILE__, array( 'P2P_Storage', 'init' ) );
register_activation_hook( __FILE__, array( 'P2P_Storage', 'install' ) );

// TODO safer handling for uninstall
//register_uninstall_hook( __FILE__, array( 'P2P_Storage', 'uninstall' ) );
