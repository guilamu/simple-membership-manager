<?php
/**
 * Plugin Name: Simple Membership Manager
 * Description: Lightweight, zero-dependency drop-in replacement for Restrict Content Pro. Compatible with the same database tables and the advanced-menu-items-visibility-control plugin.
 * Version: 1.0
 * Author: Guilamu
 * Author URI: https://github.com/guilamu
 * Text Domain: smm
 * Domain Path: languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Update URI: https://github.com/guilamu/simple-membership-manager/
 * Plugin URI: https://github.com/guilamu/simple-membership-manager
 * License: GPL-3.0-or-later
 *
 * @package Simple_Membership_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SMM_PLUGIN_FILE', __FILE__ );
define( 'SMM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SMM_VERSION', '1.0' );

if ( ! defined( 'RCP_PLUGIN_VERSION' ) ) {
	define( 'RCP_PLUGIN_VERSION', '3.5.58.1' );
}
if ( ! defined( 'RCP_PLUGIN_FILE' ) ) {
	define( 'RCP_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'RCP_PLUGIN_DIR' ) ) {
	define( 'RCP_PLUGIN_DIR', SMM_PLUGIN_DIR );
}
if ( ! defined( 'RCP_PLUGIN_URL' ) ) {
	define( 'RCP_PLUGIN_URL', SMM_PLUGIN_URL );
}

/**
 * Minimal singleton stub that mimics restrict_content_pro().
 *
 * @return object
 */
function restrict_content_pro() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new stdClass();
		$instance->components = array();
	}
	return $instance;
}

/**
 * Load RCP settings into the global for backwards compatibility.
 */
function smm_setup_globals() {
	if ( ! isset( $GLOBALS['rcp_options'] ) ) {
		$GLOBALS['rcp_options'] = get_option( 'rcp_settings', array() );
	}

	global $wpdb;

	if ( ! isset( $wpdb->rcp_membershipmeta ) ) {
		$wpdb->rcp_membershipmeta = $wpdb->prefix . 'rcp_membershipmeta';
	}

	if ( ! isset( $wpdb->levelmeta ) ) {
		$wpdb->levelmeta = $wpdb->prefix . 'rcp_subscription_level_meta';
	}
}

smm_setup_globals();

require_once SMM_PLUGIN_DIR . 'includes/db.php';
require_once SMM_PLUGIN_DIR . 'includes/class-smm-membership-level.php';
require_once SMM_PLUGIN_DIR . 'includes/class-smm-customer.php';
require_once SMM_PLUGIN_DIR . 'includes/class-smm-membership.php';
require_once SMM_PLUGIN_DIR . 'includes/functions-compat.php';
require_once SMM_PLUGIN_DIR . 'includes/emails.php';
require_once SMM_PLUGIN_DIR . 'includes/content-restriction.php';

/**
 * Load the 'rcp' text domain on 'init' so existing RCP translations carry over.
 *
 * WordPress 6.7+ requires translations to be loaded at 'init' or later.
 * RCP loaded on 'after_setup_theme' which triggered a _doing_it_wrong notice.
 */
add_action( 'init', function () {
	load_plugin_textdomain( 'rcp', false, dirname( plugin_basename( SMM_PLUGIN_FILE ) ) . '/languages' );
} );

if ( is_admin() ) {
	require_once SMM_PLUGIN_DIR . 'admin/admin-pages.php';
	require_once SMM_PLUGIN_DIR . 'admin/members-page.php';
	require_once SMM_PLUGIN_DIR . 'admin/customers-page.php';
	require_once SMM_PLUGIN_DIR . 'admin/levels-page.php';
}

require_once SMM_PLUGIN_DIR . 'includes/class-github-updater.php';

/**
 * Register SMM with Guilamu Bug Reporter
 */
add_action( 'plugins_loaded', function() {
	if ( class_exists( 'Guilamu_Bug_Reporter' ) ) {
		Guilamu_Bug_Reporter::register( array(
			'slug'        => 'simple-membership-manager',
			'name'        => 'Simple Membership Manager',
			'version'     => SMM_VERSION,
			'github_repo' => 'guilamu/simple-membership-manager',
		) );
	}
}, 20 );

/**
 * Add row meta links to SMM on the plugins screen
 */
add_filter( 'plugin_row_meta', 'smm_plugin_row_meta_links', 10, 2 );
function smm_plugin_row_meta_links( $links, $file ) {
	if ( plugin_basename( SMM_PLUGIN_FILE ) !== $file ) {
		return $links;
	}

	// 1. Report a Bug Link
	if ( class_exists( 'Guilamu_Bug_Reporter' ) ) {
		$links[] = sprintf(
			'<a href="#" class="guilamu-bug-report-btn" data-plugin-slug="simple-membership-manager" data-plugin-name="%s">%s</a>',
			esc_attr__( 'Simple Membership Manager', 'rcp' ),
			esc_html__( '🐛 Report a Bug', 'rcp' )
		);
	} else {
		$links[] = sprintf(
			'<a href="%s" target="_blank">%s</a>',
			'https://github.com/guilamu/guilamu-bug-reporter/releases',
			esc_html__( '🐛 Report a Bug (install Bug Reporter)', 'rcp' )
		);
	}

	// 2. View Details Link (Thickbox plugin details modal)
	$links[] = sprintf(
		'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
		esc_url( self_admin_url(
			'plugin-install.php?tab=plugin-information&plugin=simple-membership-manager'
			. '&TB_iframe=true&width=772&height=926'
		) ),
		esc_attr__( 'More information about Simple Membership Manager', 'rcp' ),
		esc_attr__( 'Simple Membership Manager', 'rcp' ),
		esc_html__( 'View details', 'rcp' )
	);

	return $links;
}

register_activation_hook( __FILE__, 'smm_activate' );
function smm_activate() {
	smm_setup_globals();
	if ( ! get_option( 'rcp_is_installed' ) ) {
		update_option( 'rcp_is_installed', '1' );
	}
	if ( ! get_option( 'rcp_version' ) ) {
		update_option( 'rcp_version', RCP_PLUGIN_VERSION );
	}
	if ( ! get_option( 'rcp_db_version' ) ) {
		update_option( 'rcp_db_version', '1.6' );
	}
}
