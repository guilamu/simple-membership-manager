<?php
/**
 * Plugin Name: Simple Membership Manager
 * Description: Lightweight, zero-dependency drop-in replacement for Restrict Content Pro. Compatible with the same database tables and the advanced-menu-items-visibility-control plugin.
 * Version: 1.0.0
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

	// Create database tables if they don't exist (fresh install without RCP).
	smm_create_tables();
}

/**
 * Create all required database tables using dbDelta (idempotent).
 *
 * Safe for both fresh installs and RCP migrations — dbDelta only
 * creates tables that don't already exist.
 */
function smm_create_tables() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();

	// 1. Membership Levels table (wp_restrict_content_pro)
	$levels_table = smm_db_levels_table();
	$sql = "CREATE TABLE {$levels_table} (
		id bigint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
		name varchar(200) NOT NULL,
		description longtext NOT NULL,
		duration smallint UNSIGNED NOT NULL DEFAULT 0,
		duration_unit tinytext NOT NULL,
		trial_duration smallint(6) UNSIGNED NOT NULL DEFAULT 0,
		trial_duration_unit tinytext NOT NULL,
		price tinytext NOT NULL,
		fee tinytext NOT NULL,
		maximum_renewals smallint UNSIGNED NOT NULL DEFAULT 0,
		after_final_payment tinytext NOT NULL,
		level mediumint UNSIGNED NOT NULL DEFAULT 0,
		role tinytext NOT NULL,
		status varchar(12) NOT NULL DEFAULT 'active',
		list_order mediumint UNSIGNED NOT NULL DEFAULT 0,
		date_created datetime NOT NULL,
		date_modified datetime NOT NULL,
		uuid varchar(100) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		KEY name (name(191)),
		KEY status (status)
	) {$charset_collate};";
	dbDelta( $sql );

	// 2. Membership Level Meta table (wp_rcp_subscription_level_meta)
	$level_meta_table = smm_db_level_meta_table();
	$sql = "CREATE TABLE {$level_meta_table} (
		meta_id bigint(20) NOT NULL AUTO_INCREMENT,
		level_id bigint(20) NOT NULL DEFAULT '0',
		meta_key varchar(255) DEFAULT NULL,
		meta_value longtext,
		PRIMARY KEY (meta_id),
		KEY level_id (level_id),
		KEY meta_key (meta_key(191))
	) {$charset_collate};";
	dbDelta( $sql );

	// 3. Customers table (wp_rcp_customers)
	$customers_table = smm_db_customers_table();
	$sql = "CREATE TABLE {$customers_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT '0',
		date_registered datetime NOT NULL,
		email_verification enum('verified', 'pending', 'none') DEFAULT 'none',
		last_login datetime DEFAULT NULL,
		has_trialed smallint unsigned DEFAULT NULL,
		ips longtext NOT NULL DEFAULT '',
		notes longtext NOT NULL DEFAULT '',
		uuid varchar(100) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		KEY user_id (user_id)
	) {$charset_collate};";
	dbDelta( $sql );

	// 4. Memberships table (wp_rcp_memberships)
	$memberships_table = smm_db_memberships_table();
	$sql = "CREATE TABLE {$memberships_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) unsigned NOT NULL DEFAULT '0',
		user_id bigint(20) unsigned DEFAULT NULL,
		object_id bigint(9) NOT NULL DEFAULT '0',
		object_type varchar(20) DEFAULT NULL,
		currency varchar(20) NOT NULL DEFAULT 'USD',
		initial_amount mediumtext NOT NULL,
		recurring_amount mediumtext NOT NULL,
		created_date datetime NOT NULL,
		activated_date datetime DEFAULT NULL,
		trial_end_date datetime DEFAULT NULL,
		renewed_date datetime DEFAULT NULL,
		cancellation_date datetime DEFAULT NULL,
		expiration_date datetime DEFAULT NULL,
		payment_plan_completed_date datetime DEFAULT NULL,
		auto_renew smallint unsigned NOT NULL DEFAULT '0',
		times_billed smallint unsigned NOT NULL DEFAULT '0',
		maximum_renewals smallint unsigned NOT NULL DEFAULT '0',
		status varchar(12) NOT NULL DEFAULT 'pending',
		gateway_customer_id tinytext DEFAULT NULL,
		gateway_subscription_id tinytext DEFAULT NULL,
		gateway tinytext NOT NULL DEFAULT '',
		signup_method tinytext NOT NULL DEFAULT '',
		subscription_key varchar(32) NOT NULL DEFAULT '',
		notes longtext NOT NULL DEFAULT '',
		upgraded_from bigint(20) unsigned DEFAULT NULL,
		date_modified datetime NOT NULL,
		disabled smallint unsigned DEFAULT NULL,
		uuid varchar(100) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		KEY customer_id (customer_id),
		KEY object_id (object_id),
		KEY status (status),
		KEY disabled (disabled)
	) {$charset_collate};";
	dbDelta( $sql );

	// 5. Membership Meta table (wp_rcp_membershipmeta)
	$membership_meta_table = smm_db_membership_meta_table();
	$sql = "CREATE TABLE {$membership_meta_table} (
		meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		rcp_membership_id bigint(20) unsigned NOT NULL DEFAULT '0',
		meta_key varchar(255) DEFAULT NULL,
		meta_value longtext DEFAULT NULL,
		PRIMARY KEY (meta_id),
		KEY rcp_membership_id (rcp_membership_id),
		KEY meta_key (meta_key(191))
	) {$charset_collate};";
	dbDelta( $sql );
}
