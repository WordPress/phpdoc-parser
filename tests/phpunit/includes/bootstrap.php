<?php

if ( ! getenv( 'WP_TESTS_DIR' ) ) {
	exit( '$_ENV["WP_TESTS_DIR"] is not set.' . PHP_EOL );
}

include( __DIR__ . '/../../../vendor/autoload.php' );

/**
 * The WordPress tests functions.
 *
 * Clearly, WP_TESTS_DIR should be the path to the WordPress PHPUnit tests checkout.
 *
 * We are loading this so that we can add our tests filter to load the plugin, using
 * tests_add_filter().
 *
 * @since 1.0.0
 */
require_once getenv( 'WP_TESTS_DIR' ) . '/includes/functions.php';

tests_add_filter( 'muplugins_loaded', function() {
	include( __DIR__ . '/../../../plugin.php' );
});

/**
 * Sets up the WordPress test environment.
 *
 * We've got our action set up, so we can load this now, and viola, the tests begin.
 * Again, WordPress' PHPUnit test suite needs to be installed under the given path.
 *
 * @since 1.0.0
 */
require getenv( 'WP_TESTS_DIR' ) . '/includes/bootstrap.php';

include( __DIR__ . '/export-testcase.php' );
