<?php
/**
 * Admin Pages - Menu Registration
 *
 * @package Simple_Membership_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function rcp_settings_menu() {
	add_menu_page( __( 'Restrict', 'rcp' ), __( 'Restrict', 'rcp' ), 'manage_options', 'rcp-members', 'rcp_members_page', 'dashicons-lock' );
	add_submenu_page( 'rcp-members', __( 'Memberships', 'rcp' ), __( 'Memberships', 'rcp' ), 'manage_options', 'rcp-members', 'rcp_members_page', 1 );
	add_submenu_page( 'rcp-members', __( 'Customers', 'rcp' ), __( 'Customers', 'rcp' ), 'manage_options', 'rcp-customers', 'rcp_customers_page', 2 );
	add_submenu_page( 'rcp-members', __( 'Membership Levels', 'rcp' ), __( 'Membership Levels', 'rcp' ), 'manage_options', 'rcp-member-levels', 'rcp_member_levels_page', 3 );
}
add_action( 'admin_menu', 'rcp_settings_menu' );

function smm_enqueue_admin_styles( $hook ) {
	if ( false === strpos( $hook, 'rcp-' ) && 'toplevel_page_rcp-members' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'smm-admin', SMM_PLUGIN_URL . 'admin/admin-styles.css', array(), SMM_VERSION );
}
add_action( 'admin_enqueue_scripts', 'smm_enqueue_admin_styles' );

function smm_dispatch_admin_actions() {
	if ( isset( $_POST['rcp-action'] ) ) {
		do_action( 'rcp_action_' . sanitize_key( $_POST['rcp-action'] ), $_POST );
	}
	if ( isset( $_GET['rcp-action'] ) ) {
		do_action( 'rcp_action_' . sanitize_key( $_GET['rcp-action'] ), $_GET );
	}
}
add_action( 'admin_init', 'smm_dispatch_admin_actions' );

/* --------------------------------------------------------------------- *
 * "Membership" column on the Users list table (wp-admin/users.php)
 * --------------------------------------------------------------------- */

/**
 * Register the column header.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function smm_users_columns( $columns ) {
	$columns['smm_membership'] = __( 'Membership', 'rcp' );
	return $columns;
}
add_filter( 'manage_users_columns', 'smm_users_columns' );

/**
 * Render the column content for each user row.
 *
 * @param string $output      Custom column output (empty by default).
 * @param string $column_name The column key.
 * @param int    $user_id     The current user ID.
 * @return string
 */
function smm_users_column_content( $output, $column_name, $user_id ) {
	if ( 'smm_membership' !== $column_name ) {
		return $output;
	}

	$customer = rcp_get_customer_by_user_id( $user_id );

	if ( $customer ) {
		$memberships = rcp_get_memberships( array(
			'customer_id' => $customer->get_id(),
			'number'      => 999,
			'disabled'    => 0,
		) );

		if ( ! empty( $memberships ) ) {
			$parts = array();
			foreach ( $memberships as $membership ) {
				$level_name = esc_html( $membership->get_membership_level_name() );
				$status     = $membership->get_status();
				$label      = esc_html( rcp_get_status_label( $status ) );

				$color = 'active' === $status ? 'green' : ( in_array( $status, array( 'expired', 'cancelled' ), true ) ? 'red' : 'orange' );

				$edit_url = esc_url( rcp_get_memberships_admin_page( array(
					'membership_id' => $membership->get_id(),
					'view'          => 'edit',
				) ) );

				$parts[] = sprintf(
					'<a href="%s">%s</a> — <span style="color:%s;font-weight:bold;">%s</span>',
					$edit_url,
					$level_name,
					esc_attr( $color ),
					$label
				);
			}
			return implode( '<br>', $parts );
		}
	}

	// No membership found — show "Add Membership" button.
	$user = get_userdata( $user_id );
	$add_url = rcp_get_memberships_admin_page( array(
		'view'  => 'add',
		'email' => rawurlencode( $user ? $user->user_email : '' ),
	) );

	return sprintf(
		'<a href="%s" class="button button-small">%s</a>',
		esc_url( $add_url ),
		esc_html__( 'Add Membership', 'rcp' )
	);
}
add_filter( 'manage_users_custom_column', 'smm_users_column_content', 10, 3 );
