<?php

/**
 * Plugin Name: AVC WP Parser
 * Description: Create a plugin/theme/composer-package source reference site powered by WordPress
 * Author: Ryan McCue, Paul Gibbs, Andrey "Rarst" Savchenko and Contributors
 * Author URI: https://github.com/WordPress/phpdoc-parser/graphs/contributors
 * Plugin URI: https://github.com/WordPress/phpdoc-parser
 * Version:
 * Text Domain: wp-parser
 */

define('AVC_WP_PARSER', true);

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

global $wp_parser;
$wp_parser = new WP_Parser\Plugin();
$wp_parser->on_load();

add_filter('wp_parser_exclude_directories', function () {
    return ['vendor', 'dist', 'tests', 'semantic', 'node_modules'];
});

add_filter('wp_parser_exclude_directories_strict', function () {
    return true;
});

register_activation_hook(__FILE__, ['P2P_Storage', 'init']);
register_activation_hook(__FILE__, ['P2P_Storage', 'install']);

// TODO safer handling for uninstall
// register_uninstall_hook( __FILE__, array( 'P2P_Storage', 'uninstall' ) );
