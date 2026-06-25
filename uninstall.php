<?php
/**
 * Simple Membership Manager Uninstall Cleanup
 *
 * Cleanup SMM update transients when uninstalling the plugin.
 *
 * @package Simple_Membership_Manager
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete GitHub release transient cache.
delete_transient( 'smm_github_release' );

// Also delete site-wide site transients if any exist.
delete_site_transient( 'smm_github_release' );
