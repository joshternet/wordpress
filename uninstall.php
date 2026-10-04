<?php
/**
 * Uninstall cleanup for Joshternet.
 *
 * @package Joshternet
 * @author  Joshua Morris
 * @copyright 2026 Joshua Morris
 * @license GPL-2.0-or-later
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'joshternet_enabled' );
delete_option( 'joshternet_identity' );
delete_transient( 'joshternet_button_state' );

if ( is_multisite() ) {
	$joshternet_site_ids = get_sites(
		array(
			'fields'   => 'ids',
			'number'   => 0,
			'spam'     => 0,
			'deleted'  => 0,
			'archived' => 0,
			'mature'   => 0,
		)
	);

	foreach ( $joshternet_site_ids as $joshternet_site_id ) {
		switch_to_blog( (int) $joshternet_site_id );
		delete_option( 'joshternet_enabled' );
		delete_option( 'joshternet_identity' );
		delete_transient( 'joshternet_button_state' );
		restore_current_blog();
	}
}
