<?php
/**
 * Plugin Name:       Joshternet
 * Plugin URI:        https://joshternet.org/
 * Description:       Let a WordPress site declare itself part of the Joshternet at /.well-known/josh.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Joshua Morris
 * Author URI:        https://joshuamorris.info/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       joshternet
 *
 * @package Joshternet
 * @author  Joshua Morris
 * @copyright 2026 Joshua Morris
 * @license GPL-2.0-or-later
 * @see https://joshternet.org/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JOSHTERNET_VERSION', '0.1.0' );
define( 'JOSHTERNET_PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-joshternet-origin.php';
require_once __DIR__ . '/includes/class-joshternet-endpoint.php';
require_once __DIR__ . '/includes/class-joshternet-button.php';
require_once __DIR__ . '/includes/class-joshternet-admin.php';

add_action(
	'plugins_loaded',
	static function () {
		Joshternet_Endpoint::register();
		Joshternet_Button::register();

		if ( is_admin() ) {
			Joshternet_Admin::register();
		}
	}
);
