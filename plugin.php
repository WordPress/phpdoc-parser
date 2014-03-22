<?php
/**
 * Plugin Name: WP Parser
 * Description: Create a function reference site powered by WordPress
 * Author: Ryan McCue and Paul Gibbs
 * Plugin URI: https://github.com/rmccue/WP-Parser
 * Version:
 */

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

global $wp_parser;
$wp_parser = new WP_Parser\Plugin();
$wp_parser->on_load();
