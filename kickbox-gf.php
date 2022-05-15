<?php

/**
 * Gravity Forms Kickbox Add-On
 *
 * @package           Gravity Forms Kickbox Add-On
 * @author            Alex Bondarev
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Gravity Forms Kickbox Add-On
 * Description:       Enhance Gravity Forms with email verification via Kickbox.
 * Version:           1.0.0
 * Requires PHP:      7.0
 * Author:            Alex Bondarev
 * Text Domain:       kickbox-gf
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

const KICKBOX_GF_VERSION = '1.0.0';

define( 'KICKBOX_GF_PATH', plugin_dir_path( __FILE__ ) );

if ( is_readable( KICKBOX_GF_PATH . 'lib/autoload.php' ) ) {
	require KICKBOX_GF_PATH . 'lib/autoload.php';
}

add_action( 'gform_loaded', array( 'Kickbox_GF', 'load' ), 5 );
add_action(
	'init',
	function() {
		load_plugin_textdomain( 'kickbox-gf', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

register_uninstall_hook( __FILE__, array( 'Kickbox_GF', 'uninstall' ) );

/**
 * Bootstrap class to load the Add-On.
 */
class Kickbox_GF {
	/**
	 * Loads and registers the addon.
	 *
	 * @return void
	 */
	public static function load() {
		if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
			return;
		}

		require_once 'class-kickbox-gf-cache.php';
		require_once 'class-kickbox-gf-error-messages.php';
		require_once 'class-kickbox-gf-addon.php';

		GFAddOn::register( 'Kickbox_GF_Addon' );
	}

	/**
	 * Cleans up after itself.
	 *
	 * @return void
	 */
	public static function uninstall() {
		wp_clear_scheduled_hook( Kickbox_GF_Cache::CRON_ACTION );

		delete_option( Kickbox_GF_Cache::DATABASE_KEY );
	}
}

/**
 * Get an instance of the Kickbox_GF_Addon class.
 *
 * @return Kickbox_GF_Addon
 */
function get_kickbox_gf_addon() : Kickbox_GF_Addon {
	return Kickbox_GF_Addon::get_instance();
}
