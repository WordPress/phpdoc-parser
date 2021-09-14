<?php

/**
 * Plugin Name: AVC WP Parser
 * Description: Create a plugin/theme/composer-package source reference site powered by WordPress
 * Author: Evan Shaw, Ryan McCue, Paul Gibbs, Andrey "Rarst" Savchenko and Contributors
 * Author URI: https://github.com/aivec/phpdoc-parser/graphs/contributors
 * Plugin URI: https://github.com/aivec/phpdoc-parser
 * Version: %%VERSION%%
 * Text Domain: wp-parser
 * Domain Path: /languages/
 */

define('AVC_WP_PARSER', true);
define('AVCPDP_VERSION', '%%VERSION%%');
define('AVCPDP_LANG_DIR', __DIR__ . '/languages');
define('AVCPDP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins/' . plugin_basename(dirname(__FILE__)));
define('AVCPDP_PLUGIN_URL', site_url() . '/wp-content/plugins/' . plugin_basename(dirname(__FILE__)));
load_plugin_textdomain('wp-parser', false, dirname(plugin_basename(__FILE__)) . '/languages');

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

Aivec\Plugins\DocParser\Master::init();

add_filter('wp_parser_exclude_directories', function () {
    return ['vendor', 'dist', 'tests', 'semantic', 'node_modules'];
});

add_filter('wp_parser_exclude_directories_strict', function () {
    return true;
});

register_activation_hook(__FILE__, ['P2P_Storage', 'init']);
register_activation_hook(__FILE__, ['P2P_Storage', 'install']);
register_activation_hook(__FILE__, function () {
    (new Aivec\Plugins\DocParser\Registrations())->registerPostTypes();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});

// TODO safer handling for uninstall
// register_uninstall_hook( __FILE__, array( 'P2P_Storage', 'uninstall' ) );
