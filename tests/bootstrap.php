<?php
/**
 * PHPUnit bootstrap for Joshternet.
 *
 * @package Joshternet
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, 'Could not find WordPress test library at ' . $_tests_dir . PHP_EOL );
	fwrite( STDERR, 'Run bin/install-wp-tests.sh first, or set WP_TESTS_DIR.' . PHP_EOL );
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin for tests.
 *
 * @return void
 */
function joshternet_manually_load_plugin() {
	require dirname( __DIR__ ) . '/joshternet.php';
}

tests_add_filter( 'muplugins_loaded', 'joshternet_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
