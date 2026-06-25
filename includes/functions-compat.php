<?php
/**
 * RCP Function Compatibility Layer
 *
 * All rcp_* function stubs required by the menu plugin, content restriction,
 * and the admin interface.
 *
 * @package Simple_Membership_Manager
 */

use RCP\Membership_Level;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* --------------------------------------------------------------------- *
 * Membership Level Functions
 * --------------------------------------------------------------------- */

function rcp_get_membership_level( $level_id ) {
	$row = smm_db_get_membership_level( $level_id );
	if ( ! $row ) {
		return false;
	}
	$level = new Membership_Level( $row );
	return apply_filters( 'rcp_get_level', $level );
}

function rcp_get_membership_level_by( $column_name, $column_value ) {
	$row = smm_db_get_membership_level_by( $column_name, $column_value );
	if ( ! $row ) {
		return false;
	}
	$level = new Membership_Level( $row );
	return apply_filters( 'rcp_get_level', $level );
}

function rcp_get_membership_levels( $args = array() ) {
	$rows = smm_db_get_membership_levels( $args );
	if ( ! empty( $args['fields'] ) && 'id' === $args['fields'] ) {
		return apply_filters( 'rcp_get_levels', $rows );
	}
	$levels = array();
	foreach ( $rows as $row ) {
		$levels[] = new Membership_Level( $row );
	}
	return apply_filters( 'rcp_get_levels', $levels );
}

function rcp_count_membership_levels( $args = array() ) {
	$args['count'] = true;
	return smm_db_get_membership_levels( $args );
}

function rcp_add_membership_level( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'name'                => '',
		'description'         => '',
		'duration'            => 0,
		'duration_unit'       => 'month',
		'trial_duration'      => 0,
		'trial_duration_unit' => 'day',
		'price'               => 0,
		'fee'                 => 0,
		'maximum_renewals'    => 0,
		'after_final_payment' => '',
		'list_order'          => 0,
		'level'               => 0,
		'status'              => 'inactive',
		'role'                => 'subscriber',
	) );

	do_action( 'rcp_pre_add_subscription', $args );
	$args = apply_filters( 'rcp_add_subscription_args', $args );

	foreach ( array( 'price', 'fee' ) as $key ) {
		if ( empty( $args[ $key ] ) ) {
			$args[ $key ] = '0';
		}
		$args[ $key ] = str_replace( ',', '', $args[ $key ] );
	}

	if ( empty( $args['maximum_renewals'] ) ) {
		$args['maximum_renewals']    = 0;
		$args['after_final_payment'] = '';
	}

	$membership_level_id = smm_db_insert_membership_level( $args );
	if ( ! $membership_level_id ) {
		return new WP_Error( 'level_not_added', __( 'An unexpected error occurred while trying to add the membership level.', 'rcp' ) );
	}

	do_action( 'rcp_add_subscription', $membership_level_id, $args );
	return absint( $membership_level_id );
}

function rcp_update_membership_level( $level_id, $args = array() ) {
	$level = rcp_get_membership_level( $level_id );
	if ( ! $level instanceof Membership_Level ) {
		return new WP_Error( 'invalid_level', __( 'Invalid membership level.', 'rcp' ) );
	}

	do_action( 'rcp_pre_edit_subscription_level', $level_id, $args );

	foreach ( array( 'price', 'fee' ) as $key ) {
		if ( empty( $args[ $key ] ) ) {
			$args[ $key ] = '0';
		}
		$args[ $key ] = str_replace( ',', '', $args[ $key ] );
	}

	if ( empty( $args['maximum_renewals'] ) ) {
		$args['maximum_renewals']    = 0;
		$args['after_final_payment'] = '';
	}

	$updated = smm_db_update_membership_level( $level_id, $args );
	do_action( 'rcp_edit_subscription_level', $level_id, $args );

	if ( ! $updated ) {
		return new WP_Error( 'level_not_added', __( 'An unexpected error occurred while trying to update the membership level.', 'rcp' ) );
	}
	return true;
}

function rcp_delete_membership_level( $level_id ) {
	$deleted = smm_db_delete_membership_level( $level_id );
	if ( ! $deleted ) {
		return false;
	}
	do_action( 'rcp_remove_level', absint( $level_id ) );
	return true;
}

/* --------------------------------------------------------------------- *
 * Level Meta Functions
 * --------------------------------------------------------------------- */

function rcp_add_membership_level_meta( $level_id, $meta_key, $meta_value, $unique = false ) {
	return smm_db_update_membership_level_meta( $level_id, $meta_key, $meta_value );
}

function rcp_delete_membership_level_meta( $level_id, $meta_key, $meta_value = '' ) {
	return smm_db_delete_membership_level_meta( $level_id, $meta_key );
}

function rcp_get_membership_level_meta( $level_id, $key = '', $single = false ) {
	return smm_db_get_membership_level_meta( $level_id, $key, $single );
}

function rcp_update_membership_level_meta( $level_id, $meta_key, $meta_value, $prev_value = '' ) {
	return smm_db_update_membership_level_meta( $level_id, $meta_key, $meta_value );
}

/* --------------------------------------------------------------------- *
 * Customer Functions
 * --------------------------------------------------------------------- */

function rcp_get_customer( $customer_id = 0 ) {
	if ( empty( $customer_id ) ) {
		return rcp_get_customer_by_user_id( get_current_user_id() );
	}
	$row = smm_db_get_customer( $customer_id );
	if ( ! $row ) {
		return false;
	}
	return new RCP_Customer( $row );
}

function rcp_get_customer_by( $field = '', $value = '' ) {
	$row = smm_db_get_customer_by( $field, $value );
	if ( ! $row ) {
		return false;
	}
	return new RCP_Customer( $row );
}

function rcp_get_customer_by_user_id( $user_id = 0 ) {
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}
	return rcp_get_customer_by( 'user_id', $user_id );
}

function rcp_get_customers( $args = array() ) {
	$rows = smm_db_get_customers( $args );
	$customers = array();
	foreach ( $rows as $row ) {
		$customers[] = new RCP_Customer( $row );
	}
	return $customers;
}

function rcp_count_customers( $args = array() ) {
	$args['count'] = true;
	return smm_db_get_customers( $args );
}

function rcp_add_customer( $data = array() ) {
	$data = wp_parse_args( $data, array(
		'date_registered' => current_time( 'mysql' ),
		'has_trialed'     => 0,
	) );

	if ( ! empty( $data['user_id'] ) ) {
		$data['user_id'] = absint( $data['user_id'] );
	} else {
		$user_args = ! empty( $data['user_args'] ) ? $data['user_args'] : array();
		$required  = array( 'user_login', 'user_email', 'user_pass' );
		foreach ( $required as $field ) {
			if ( empty( $user_args[ $field ] ) ) {
				return false;
			}
		}
		$user_id = wp_insert_user( $user_args );
		if ( is_wp_error( $user_id ) ) {
			return false;
		}
		$data['user_id'] = absint( $user_id );
	}

	if ( empty( $data['user_id'] ) ) {
		return false;
	}

	$existing = rcp_get_customer_by_user_id( $data['user_id'] );
	if ( ! empty( $existing ) ) {
		return false;
	}

	if ( ! empty( $data['ips'] ) ) {
		$data['ips'] = maybe_serialize( $data['ips'] );
	}

	return smm_db_insert_customer( $data );
}

function rcp_update_customer( $customer_id, $data = array() ) {
	$customer = rcp_get_customer( $customer_id );
	return $customer ? $customer->update( $data ) : false;
}

function rcp_delete_customer( $customer_id ) {
	$customer = rcp_get_customer( $customer_id );
	if ( empty( $customer ) ) {
		return false;
	}
	$customer->disable_memberships();
	return smm_db_delete_customer( $customer_id );
}

function rcp_get_customer_single_membership( $customer_id ) {
	$args = array(
		'customer_id' => absint( $customer_id ),
		'number'      => 1,
		'orderby'     => 'id',
		'order'       => 'ASC',
	);
	$args = apply_filters( 'rcp_customer_single_membership_query_args', $args, $customer_id );
	$memberships = rcp_get_memberships( $args );
	if ( is_array( $memberships ) && isset( $memberships[0] ) ) {
		return $memberships[0];
	}
	return false;
}

function rcp_get_customer_memberships( $customer_id, $args = array() ) {
	$customer = rcp_get_customer( $customer_id );
	if ( ! $customer ) {
		return array();
	}
	return $customer->get_memberships( $args );
}

function rcp_add_customer_note( $customer_id = 0, $note = '' ) {
	$customer = rcp_get_customer( $customer_id );
	if ( ! is_object( $customer ) ) {
		return;
	}
	$customer->add_note( $note );
}

function rcp_get_customer_membership_level_ids( $customer_id = 0 ) {
	$customer = rcp_get_customer( $customer_id );
	if ( ! is_object( $customer ) ) {
		return array();
	}
	$ids = array();
	$memberships = $customer->get_memberships( array( 'status' => array( 'active', 'cancelled' ) ) );
	if ( empty( $memberships ) ) {
		return array();
	}
	foreach ( $memberships as $membership ) {
		$ids[] = $membership->get_object_id();
	}
	return $ids;
}

function rcp_get_customer_membership_level_names( $customer_id = 0 ) {
	$customer = rcp_get_customer( $customer_id );
	if ( ! is_object( $customer ) ) {
		return array();
	}
	$names = array();
	$memberships = $customer->get_memberships( array( 'status' => array( 'active', 'cancelled' ) ) );
	if ( empty( $memberships ) ) {
		return array();
	}
	foreach ( $memberships as $membership ) {
		$names[] = $membership->get_membership_level_name();
	}
	return $names;
}

function rcp_disable_customer_memberships( $customer_id = 0 ) {
	$customer = rcp_get_customer( $customer_id );
	if ( ! is_object( $customer ) ) {
		return;
	}
	$customer->disable_memberships();
}

function rcp_customer_has_trialed( $customer_id = 0 ) {
	$customer = rcp_get_customer( $customer_id );
	if ( ! is_object( $customer ) ) {
		return false;
	}
	return $customer->has_trialed();
}

/* --------------------------------------------------------------------- *
 * Membership Functions
 * --------------------------------------------------------------------- */

function rcp_get_membership( $membership_id ) {
	$row = smm_db_get_membership( $membership_id );
	if ( ! $row ) {
		return false;
	}
	return new RCP_Membership( $row );
}

function rcp_get_membership_by( $field = '', $value = '' ) {
	$memberships = rcp_get_memberships( array(
		$field   => $value,
		'number' => 1,
	) );
	if ( empty( $memberships ) ) {
		return false;
	}
	return reset( $memberships );
}

function rcp_get_memberships( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'number'   => 20,
		'disabled' => 0,
	) );
	$rows = smm_db_get_memberships( $args );
	if ( ! empty( $args['fields'] ) && 'id' === $args['fields'] ) {
		return $rows;
	}
	$memberships = array();
	foreach ( $rows as $row ) {
		$memberships[] = new RCP_Membership( $row );
	}
	return $memberships;
}

function rcp_count_memberships( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'count'    => true,
		'disabled' => 0,
	) );
	return smm_db_get_memberships( $args );
}

function rcp_add_membership( $data = array() ) {
	$defaults = array(
		'customer_id'      => 0,
		'user_id'          => null,
		'object_id'        => 0,
		'object_type'      => 'membership',
		'currency'         => rcp_get_currency(),
		'initial_amount'   => false,
		'recurring_amount' => false,
		'created_date'     => current_time( 'mysql' ),
		'expiration_date'  => '',
		'auto_renew'       => false,
		'times_billed'     => 0,
		'maximum_renewals' => 0,
		'status'           => 'pending',
		'signup_method'    => 'live',
		'disabled'         => 0,
	);
	$data = wp_parse_args( $data, $defaults );

	if ( empty( $data['customer_id'] ) ) {
		return false;
	}

	$membership_level = rcp_get_membership_level( $data['object_id'] );
	if ( ! $membership_level instanceof Membership_Level ) {
		return false;
	}

	$customer = rcp_get_customer( $data['customer_id'] );

	if ( empty( $data['user_id'] ) && $customer instanceof RCP_Customer ) {
		$data['user_id'] = $customer->get_user_id();
	}

	if ( ! empty( $data['status'] ) && 'free' === $data['status'] ) {
		$data['status'] = 'active';
	}

	if ( false === $data['initial_amount'] ) {
		$data['initial_amount'] = $membership_level->get_fee() + $membership_level->get_price();
	}
	if ( false === $data['recurring_amount'] ) {
		$data['recurring_amount'] = $membership_level->get_price();
	}

	if ( empty( $data['initial_amount'] ) || '0' === $data['initial_amount'] ) {
		$data['initial_amount'] = '0.00';
	}
	if ( empty( $data['recurring_amount'] ) || '0' === $data['recurring_amount'] ) {
		$data['recurring_amount'] = '0.00';
	}

	$data['auto_renew'] = ! empty( $data['auto_renew'] ) ? 1 : 0;

	if ( empty( $data['subscription_key'] ) ) {
		$data['subscription_key'] = rcp_generate_subscription_key();
	}

	$membership_id = smm_db_insert_membership( $data );
	if ( $membership_id ) {
		do_action( 'rcp_new_membership_added', $membership_id, $data );
		return $membership_id;
	}
	return false;
}

function rcp_update_membership( $membership_id, $data = array() ) {
	$membership = rcp_get_membership( $membership_id );
	return $membership ? $membership->update( $data ) : false;
}

function rcp_delete_membership( $membership_id ) {
	return smm_db_delete_membership( $membership_id );
}

/**
 * Activate a membership that was inserted with an 'active' status.
 *
 * Mirrors RCP's rcp_activate_membership_on_insert(): assigns the user role,
 * fires activation hooks, and triggers the activation email.
 *
 * @param int   $membership_id
 * @param array $data
 */
function rcp_activate_membership_on_insert( $membership_id, $data ) {
	$status = isset( $data['status'] ) ? $data['status'] : '';
	if ( 'active' !== $status && 'free' !== $status ) {
		return;
	}
	$membership = rcp_get_membership( $membership_id );
	if ( $membership instanceof RCP_Membership && 'active' === $membership->get_status() ) {
		$membership->activate();
	}
}
add_action( 'rcp_new_membership_added', 'rcp_activate_membership_on_insert', 10, 2 );

/**
 * Clean up customer and membership records when a WordPress user is deleted.
 *
 * @param int $user_id
 */
function rcp_disable_memberships_on_user_delete( $user_id ) {
	$customer = rcp_get_customer_by_user_id( $user_id );
	if ( empty( $customer ) ) {
		return;
	}
	// rcp_delete_customer() disables the customer's memberships before deletion.
	rcp_delete_customer( $customer->get_id() );
}
add_action( 'delete_user', 'rcp_disable_memberships_on_user_delete' );

/* --------------------------------------------------------------------- *
 * Membership Meta Functions
 * --------------------------------------------------------------------- */

function rcp_add_membership_meta( $membership_id, $meta_key, $meta_value, $unique = false ) {
	return add_metadata( 'rcp_membership', $membership_id, $meta_key, $meta_value, $unique );
}

function rcp_delete_membership_meta( $membership_id, $meta_key, $meta_value = '' ) {
	return delete_metadata( 'rcp_membership', $membership_id, $meta_key, $meta_value );
}

function rcp_get_membership_meta( $membership_id, $key = '', $single = false ) {
	return get_metadata( 'rcp_membership', $membership_id, $key, $single );
}

function rcp_update_membership_meta( $membership_id, $meta_key, $meta_value, $prev_value = '' ) {
	return update_metadata( 'rcp_membership', $membership_id, $meta_key, $meta_value, $prev_value );
}

function rcp_delete_membership_meta_by_key( $meta_key ) {
	return delete_metadata( 'rcp_membership', null, $meta_key, '', true );
}

/* --------------------------------------------------------------------- *
 * User Status Functions
 * --------------------------------------------------------------------- */

function rcp_user_has_active_membership( $user_id = 0 ) {
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}
	$customer = rcp_get_customer_by_user_id( $user_id );
	$has = false;
	if ( ! empty( $customer ) ) {
		$has = $customer->has_active_membership();
	}
	return apply_filters( 'rcp_user_has_active_membership', $has, $user_id, $customer );
}

function rcp_user_has_paid_membership( $user_id = 0 ) {
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}
	$customer = rcp_get_customer_by_user_id( $user_id );
	$has = false;
	if ( ! empty( $customer ) ) {
		$has = $customer->has_paid_membership();
	}
	return apply_filters( 'rcp_user_has_paid_membership', $has, $user_id, $customer );
}

function rcp_user_has_free_membership( $user_id = 0 ) {
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}
	$customer = rcp_get_customer_by_user_id( $user_id );
	$has = false;
	if ( ! empty( $customer ) ) {
		$memberships = $customer->get_memberships( array( 'status' => 'active' ) );
		if ( $memberships ) {
			foreach ( $memberships as $membership ) {
				if ( ! $membership->is_paid() ) {
					$has = true;
					break;
				}
			}
		}
	}
	return apply_filters( 'rcp_user_has_free_membership', $has, $user_id, $customer );
}

function rcp_user_has_expired_membership( $user_id = 0 ) {
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}
	$customer = rcp_get_customer_by_user_id( $user_id );
	$has = false;
	if ( ! empty( $customer ) ) {
		$expired = $customer->get_memberships( array( 'status' => 'expired' ) );
		$has = ! empty( $expired );
	}
	return apply_filters( 'rcp_user_has_expired_membership', $has, $user_id, $customer );
}

function rcp_user_has_access( $user_id = 0, $access_level_needed = 0 ) {
	if ( empty( $user_id ) && is_user_logged_in() ) {
		$user_id = get_current_user_id();
	}
	$has = false;
	$customer = rcp_get_customer_by_user_id( $user_id );
	if ( ! empty( $customer ) ) {
		$has = $customer->has_access_level( $access_level_needed );
	}
	return apply_filters( 'rcp_user_has_access_level', $has, $user_id, $access_level_needed );
}

/* --------------------------------------------------------------------- *
 * Content Restriction Functions
 * --------------------------------------------------------------------- */

function rcp_user_can_access( $user_id = 0, $post_id = 0 ) {
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}
	$customer = rcp_get_customer_by_user_id( $user_id );

	if ( empty( $post_id ) ) {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) ) {
			return true;
		}
		$post_id = $post->ID;
	}

	if ( empty( $customer ) ) {
		$member = new RCP_Member( $user_id );
		if ( user_can( $user_id, 'manage_options' ) ) {
			$can_access = true;
		} else {
			$can_access = ! rcp_is_restricted_content( $post_id );
		}
		$can_access = apply_filters( 'rcp_member_can_access', $can_access, $member->ID, $post_id, $member );
	} else {
		$can_access = $customer->can_access( $post_id );
	}

	if ( is_user_logged_in() && ! $can_access ) {
		$restrictions = rcp_get_post_restrictions( $post_id );
		if ( empty( $restrictions['membership_levels'] ) && empty( $restrictions['access_level'] ) && ! empty( $restrictions['user_level'] ) ) {
			foreach ( $restrictions['user_level'] as $role ) {
				if ( user_can( $user_id, $role ) ) {
					$can_access = true;
					break;
				}
			}
		}
	}

	return $can_access;
}

function rcp_is_restricted_content( $post_id ) {
	if ( empty( $post_id ) || ! is_numeric( $post_id ) ) {
		return false;
	}
	$post_id = absint( $post_id );
	$restricted = rcp_is_restricted_post_type( get_post_type( $post_id ) );
	if ( ! $restricted ) {
		$restricted = rcp_has_post_restrictions( $post_id );
	}
	if ( ! $restricted ) {
		$restricted = rcp_has_term_restrictions( $post_id );
	}
	return apply_filters( 'rcp_is_restricted_content', $restricted, $post_id );
}

function rcp_has_post_restrictions( $post_id ) {
	if ( empty( $post_id ) || ! is_numeric( $post_id ) ) {
		return false;
	}
	$restricted = false;
	$post_id = absint( $post_id );

	if ( ! $restricted && get_post_meta( $post_id, '_is_paid', true ) ) {
		$restricted = true;
	}
	if ( ! $restricted && rcp_get_content_subscription_levels( $post_id ) ) {
		$restricted = true;
	}
	if ( ! $restricted ) {
		$rcp_user_level = get_post_meta( $post_id, 'rcp_user_level', true );
		if ( ! empty( $rcp_user_level ) && ! is_array( $rcp_user_level ) ) {
			$rcp_user_level = array( $rcp_user_level );
		}
		if ( ! empty( $rcp_user_level ) && 'all' !== strtolower( $rcp_user_level[0] ) && 'None' !== strtolower( $rcp_user_level[0] ) ) {
			$restricted = true;
		}
	}
	if ( ! $restricted ) {
		$rcp_access_level = get_post_meta( $post_id, 'rcp_access_level', true );
		if ( ! empty( $rcp_access_level ) && 'None' !== $rcp_access_level ) {
			$restricted = true;
		}
	}
	return (bool) apply_filters( 'rcp_has_post_restrictions', $restricted, $post_id );
}

function rcp_has_term_restrictions( $post_id ) {
	if ( empty( $post_id ) || ! is_numeric( $post_id ) ) {
		return false;
	}
	$restricted = false;
	$term_ids = rcp_get_connected_term_ids( $post_id );
	if ( empty( $term_ids ) ) {
		return $restricted;
	}
	foreach ( $term_ids as $term_id ) {
		if ( rcp_get_term_restrictions( $term_id ) ) {
			$restricted = true;
			break;
		}
	}
	return $restricted;
}

function rcp_get_post_restrictions( $post_id ) {
	$restrictions = array(
		'membership_levels' => '',
		'access_level'      => 0,
		'user_level'        => array(),
	);
	$post_type_restrictions = rcp_get_post_type_restrictions( get_post_type( $post_id ) );
	if ( empty( $post_type_restrictions ) ) {
		$membership_levels = rcp_get_content_subscription_levels( $post_id );
		$access_level = get_post_meta( $post_id, 'rcp_access_level', true );
		$user_level   = get_post_meta( $post_id, 'rcp_user_level', true );
	} else {
		$membership_levels = array_key_exists( 'subscription_level', $post_type_restrictions ) ? $post_type_restrictions['subscription_level'] : false;
		$access_level = array_key_exists( 'access_level', $post_type_restrictions ) ? $post_type_restrictions['access_level'] : false;
		$user_level   = array_key_exists( 'user_level', $post_type_restrictions ) ? $post_type_restrictions['user_level'] : false;
	}
	if ( ! empty( $user_level ) && ! is_array( $user_level ) ) {
		$user_level = array( $user_level );
	}
	if ( ! empty( $membership_levels ) ) {
		$restrictions['membership_levels'] = $membership_levels;
	}
	if ( ! empty( $access_level ) ) {
		$restrictions['access_level'] = $access_level;
	}
	if ( ! empty( $user_level ) && 'all' !== strtolower( $user_level[0] ) && 'None' !== strtolower( $user_level[0] ) ) {
		$restrictions['user_level'] = $user_level;
	}
	return $restrictions;
}

function rcp_get_term_restrictions( $term_id ) {
	if ( ! function_exists( 'get_term_meta' ) || ! $restrictions = get_term_meta( $term_id, 'rcp_restricted_meta', true ) ) {
		$restrictions = get_option( "rcp_category_meta_$term_id" );
	}
	if ( ! empty( $restrictions['access_level'] ) && 'none' === strtolower( $restrictions['access_level'] ) ) {
		unset( $restrictions['access_level'] );
	}
	return apply_filters( 'rcp_get_term_restrictions', $restrictions, $term_id );
}

function rcp_get_post_type_restrictions( $post_type ) {
	$restricted_post_types = rcp_get_restricted_post_types();
	if ( empty( $post_type ) || empty( $restricted_post_types ) ) {
		return array();
	}
	return array_key_exists( $post_type, $restricted_post_types ) ? $restricted_post_types[ $post_type ] : array();
}

function rcp_is_restricted_post_type( $post_type ) {
	$restrictions = rcp_get_post_type_restrictions( $post_type );
	return ! empty( $restrictions );
}

function rcp_get_restricted_post_types() {
	return get_option( 'rcp_restricted_post_types', array() );
}

function rcp_get_content_subscription_levels( $post_id = 0 ) {
	$levels = get_post_meta( $post_id, 'rcp_subscription_level', true );
	if ( 'all' === $levels ) {
		return false;
	}
	if ( 'any' !== $levels && 'any-paid' !== $levels && ! empty( $levels ) && ! is_array( $levels ) ) {
		$levels = array( $levels );
	}
	return apply_filters( 'rcp_get_content_subscription_levels', $levels, $post_id );
}

function rcp_get_restricted_content_message( $paid = false ) {
	global $post, $rcp_options;
	if ( ! empty( $rcp_options['restriction_message'] ) ) {
		return apply_filters( 'rcp_restricted_content_message', $rcp_options['restriction_message'] );
	}
	$message = __( 'This content is restricted to subscribers', 'rcp' );
	if ( ! empty( $rcp_options['free_message'] ) ) {
		$message = $rcp_options['free_message'];
	}
	$post_id = is_a( $post, 'WP_Post' ) ? $post->ID : 0;
	if ( ! empty( $rcp_options['paid_message'] ) && ( rcp_is_paid_content( $post_id ) || ( $post_id && rcp_has_term_restrictions( $post_id ) ) || $paid ) ) {
		$message = $rcp_options['paid_message'];
	}
	return apply_filters( 'rcp_restricted_content_message', $message );
}

function rcp_format_teaser( $message ) {
	global $post, $rcp_options;
	$show_excerpt = isset( $rcp_options['content_excerpts'] ) ? $rcp_options['content_excerpts'] : 'individual';
	$post_id = is_a( $post, 'WP_Post' ) ? $post->ID : 0;
	if ( 'always' === $show_excerpt || ( $post_id && 'individual' === $show_excerpt && get_post_meta( $post_id, 'rcp_show_excerpt', true ) ) ) {
		$excerpt_length = apply_filters( 'rcp_filter_excerpt_length', 100 );
		$excerpt = rcp_excerpt_by_id( $post, $excerpt_length );
		$message = apply_filters( 'rcp_restricted_message', $message );
		$message = $excerpt . $message;
	} else {
		$message = apply_filters( 'rcp_restricted_message', $message );
	}
	return $message;
}

function rcp_excerpt_by_id( $post, $length = 50, $tags = '<a><em><strong><blockquote><ul><ol><li><p>', $extra = ' . . .' ) {
	if ( is_int( $post ) ) {
		$post = get_post( $post );
	} elseif ( ! is_object( $post ) ) {
		return false;
	}
	$more = false;
	if ( has_excerpt( $post->ID ) ) {
		$the_excerpt = $post->post_excerpt;
	} elseif ( strstr( $post->post_content, '<!--more-->' ) ) {
		$more = true;
		$length = strpos( $post->post_content, '<!--more-->' );
		$the_excerpt = $post->post_content;
	} else {
		$the_excerpt = $post->post_content;
	}
	$tags = apply_filters( 'rcp_excerpt_tags', $tags );
	if ( $more ) {
		$the_excerpt = strip_shortcodes( strip_tags( stripslashes( substr( $the_excerpt, 0, $length ) ), $tags ) );
	} else {
		$the_excerpt = strip_shortcodes( strip_tags( stripslashes( $the_excerpt ), $tags ) );
		$the_excerpt = preg_split( '/\b/', $the_excerpt, $length * 2 + 1 );
		array_pop( $the_excerpt );
		$the_excerpt = implode( $the_excerpt );
		$the_excerpt .= $extra;
	}
	$the_excerpt = wpautop( $the_excerpt );
	return apply_filters( 'rcp_post_excerpt', $the_excerpt, $post, $length, $tags, $extra );
}

function rcp_get_connected_term_ids( $post_id = 0 ) {
	$taxonomies = array_values( get_taxonomies( array( 'public' => true ) ) );
	$terms = wp_get_object_terms( $post_id, $taxonomies, array( 'fields' => 'ids' ) );
	return $terms;
}

function rcp_is_paid_content( $post_id ) {
	if ( empty( $post_id ) || ! is_numeric( $post_id ) ) {
		$post_id = get_the_ID();
	}
	$return = false;
	$post_type_restrictions = rcp_get_post_type_restrictions( get_post_type( $post_id ) );
	if ( ! empty( $post_type_restrictions ) ) {
		if ( array_key_exists( 'is_paid', $post_type_restrictions ) ) {
			$return = true;
		}
	} else {
		$is_paid = get_post_meta( $post_id, '_is_paid', true );
		if ( $is_paid ) {
			$return = true;
		}
	}
	return (bool) apply_filters( 'rcp_is_paid_content', $return, $post_id );
}

function rcp_is_pending_verification( $user_id = 0 ) {
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}
	$customer = rcp_get_customer_by_user_id( $user_id );
	if ( empty( $customer ) ) {
		return false;
	}
	return $customer->is_pending_verification();
}

function rcp_is_trialing( $user_id = 0 ) {
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}
	$customer = rcp_get_customer_by_user_id( $user_id );
	if ( empty( $customer ) ) {
		return false;
	}
	foreach ( $customer->get_memberships() as $membership ) {
		if ( $membership->is_trialing() ) {
			return true;
		}
	}
	return false;
}

/* --------------------------------------------------------------------- *
 * Utility Functions
 * --------------------------------------------------------------------- */

function rcp_multiple_memberships_enabled() {
	return true;
}

function rcp_get_status_label( $status = '' ) {
	static $labels = null;
	if ( null === $labels ) {
		$labels = array(
			'active'    => __( 'Active', 'rcp' ),
			'inactive'  => __( 'Inactive', 'rcp' ),
			'pending'   => __( 'Pending', 'rcp' ),
			'cancelled' => __( 'Cancelled', 'rcp' ),
			'expired'   => __( 'Expired', 'rcp' ),
			'free'      => __( 'Free', 'rcp' ),
		);
	}
	return isset( $labels[ $status ] ) ? $labels[ $status ] : ucwords( $status );
}

function rcp_log( $message = '', $force = false ) {
	if ( ! $force && ! defined( 'RCP_DEBUG' ) ) {
		return;
	}
	if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		error_log( '[SMM] ' . $message );
	}
}

function rcp_is_rcp_admin_page() {
	$is_admin = false;
	$screen = get_current_screen();
	if ( $screen ) {
		$pages = array( 'toplevel_page_rcp-members', 'restrict_page_rcp-members', 'restrict_page_rcp-customers', 'restrict_page_rcp-member-levels' );
		if ( 'post' === $screen->base && ! empty( $screen->post_type ) && in_array( $screen->post_type, rcp_get_metabox_post_types(), true ) ) {
			$pages[] = $screen->id;
		}
		$is_admin = in_array( $screen->id, $pages, true );
		if ( false !== strpos( $screen->id, 'rcp-' ) || false !== strpos( $screen->id, 'restrict' ) ) {
			$is_admin = true;
		}
	}
	return apply_filters( 'rcp_is_rcp_admin_page', $is_admin, $screen );
}

function rcp_get_metabox_post_types() {
	$post_types = get_post_types( array( 'public' => true, 'show_ui' => true ) );
	$post_types = (array) apply_filters( 'rcp_metabox_post_types', $post_types );
	$exclude = apply_filters( 'rcp_metabox_excluded_post_types', array( 'forum', 'topic', 'reply', 'product', 'attachment' ) );
	return array_diff( $post_types, $exclude );
}

function rcp_get_subscription_member_count( $id, $status = 'active' ) {
	return smm_db_count_members_by_level( $id, $status );
}

function rcp_get_subscription_name( $id ) {
	$level = rcp_get_membership_level( $id );
	if ( ! $level instanceof Membership_Level ) {
		return '';
	}
	return $level->get_name();
}

function rcp_get_subscription_access_level( $id ) {
	$level = rcp_get_membership_level( $id );
	if ( ! $level instanceof Membership_Level ) {
		return false;
	}
	return $level->get_access_level();
}

function rcp_get_subscription_price( $id ) {
	$level = rcp_get_membership_level( $id );
	if ( ! $level instanceof Membership_Level ) {
		return false;
	}
	return $level->get_price();
}

function rcp_get_subscription_fee( $id ) {
	$level = rcp_get_membership_level( $id );
	if ( ! $level instanceof Membership_Level ) {
		return false;
	}
	return $level->get_fee();
}

function rcp_get_subscription_description( $id ) {
	$level = rcp_get_membership_level( $id );
	if ( ! $level instanceof Membership_Level ) {
		return '';
	}
	return $level->get_description();
}

function rcp_get_subscription_length( $id ) {
	$level = rcp_get_membership_level( $id );
	if ( ! $level instanceof Membership_Level ) {
		return false;
	}
	$details = new stdClass();
	$details->duration = $level->get_duration();
	$details->duration_unit = $level->get_duration_unit();
	return $details;
}

function rcp_get_member_levels( $_customer ) {
	$user_memberships_levels = array();
	$user_memberships = rcp_get_customer_memberships( $_customer->get_id(), array( 'status' => 'active' ) );
	if ( ! empty( $user_memberships ) && is_array( $user_memberships ) ) {
		foreach ( $user_memberships as $user_membership ) {
			$membership_id = $user_membership->get_object_id();
			$membership_level = rcp_get_membership_level( $membership_id );
			if ( $membership_level instanceof Membership_Level ) {
				$user_memberships_levels[] = $membership_level->get_access_level();
			}
		}
	}
	return $user_memberships_levels;
}

function check_array_only_numbers( $_array ) {
	foreach ( $_array as $level ) {
		if ( ! is_numeric( $level ) ) {
			return false;
		}
	}
	return true;
}

function rcp_generate_subscription_key() {
	return apply_filters( 'rcp_subscription_key', urlencode( strtolower( md5( uniqid() ) ) ) );
}

function rcp_get_access_levels() {
	$levels = array(
		0  => 'None', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5',
		6  => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10',
	);
	return apply_filters( 'rcp_access_levels', $levels );
}

function rcp_filter_duration_unit( $unit, $length ) {
	$new_unit = '';
	switch ( $unit ) {
		case 'day':
			$new_unit = $length > 1 ? __( 'Days', 'rcp' ) : __( 'Day', 'rcp' );
			break;
		case 'month':
			$new_unit = $length > 1 ? __( 'Months', 'rcp' ) : __( 'Month', 'rcp' );
			break;
		case 'year':
			$new_unit = $length > 1 ? __( 'Years', 'rcp' ) : __( 'Year', 'rcp' );
			break;
	}
	return $new_unit;
}

function rcp_calculate_subscription_expiration( $id, $set_trial = false, $upgraded_from = 0 ) {
	$membership_level = rcp_get_membership_level( $id );
	$expiration_date = 'none';
	if ( ! $membership_level instanceof Membership_Level ) {
		return $expiration_date;
	}
	if ( ! $membership_level->is_lifetime() ) {
		$current_time = current_time( 'timestamp' );
		if ( $set_trial && $membership_level->has_trial() ) {
			$expiration_unit = $membership_level->get_trial_duration_unit();
			$expiration_length = $membership_level->get_trial_duration();
		} else {
			$expiration_unit = $membership_level->get_duration_unit();
			$expiration_length = $membership_level->get_duration();
		}
		$expiration_timestamp = strtotime( '+' . $expiration_length . ' ' . $expiration_unit . ' 23:59:59', $current_time );
		$expiration_date = date( 'Y-m-d H:i:s', $expiration_timestamp );
		$extension_days = array( '29', '30', '31' );
		if ( in_array( date( 'j', $expiration_timestamp ), $extension_days, true ) && 'month' === $expiration_unit ) {
			$month = date( 'n', $expiration_timestamp );
			if ( $month < 12 ) {
				$month += 1;
				$year = date( 'Y', $expiration_timestamp );
			} else {
				$month = 1;
				$year = date( 'Y', $expiration_timestamp ) + 1;
			}
			$expiration_date = date( 'Y-m-d 23:59:59', mktime( 0, 0, 0, $month, 1, $year ) );
		}
	}
	$expiration_date = apply_filters( 'rcp_calculate_membership_level_expiration', $expiration_date, $membership_level, $set_trial );
	return $expiration_date;
}

function rcp_get_days_in_cycle( $duration_unit, $duration ) {
	$days = 0;
	switch ( $duration_unit ) {
		case 'day':  $days = $duration; break;
		case 'week': $days = $duration * 7; break;
		case 'month': $days = $duration * 30.4375; break;
		case 'year':  $days = $duration * 365.25; break;
	}
	return $days;
}

function rcp_get_currency() {
	global $rcp_options;
	$currency = isset( $rcp_options['currency'] ) ? $rcp_options['currency'] : 'USD';
	return apply_filters( 'rcp_currency', $currency );
}

function rcp_currency_filter( $price ) {
	$currency = rcp_get_currency();
	$symbols = array(
		'USD' => '$', 'EUR' => '&euro;', 'GBP' => '&pound;', 'AUD' => '$', 'CAD' => '$',
		'JPY' => '&yen;', 'BRL' => 'R$', 'CZK' => 'K&#269;', 'DKK' => 'kr',
		'HKD' => '$', 'HUF' => 'Ft', 'ILS' => '&#8362;', 'INR' => '&#8377;',
		'NOK' => 'kr', 'NZD' => '$', 'PHP' => '&#8369;', 'PLN' => '&#122;&#322;',
		'SGD' => '$', 'SEK' => 'kr', 'CHF' => 'Fr', 'TWD' => 'NT$', 'THB' => '&#3647;',
	);
	$symbol = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : '$';
	return $symbol . number_format( (float) $price, 2, '.', '' );
}

function rcp_get_currency_symbol() {
	$currency = rcp_get_currency();
	$symbols = array(
		'USD' => '$', 'EUR' => '&euro;', 'GBP' => '&pound;', 'AUD' => '$', 'CAD' => '$',
		'JPY' => '&yen;', 'BRL' => 'R$', 'INR' => '&#8377;', 'SEK' => 'kr', 'NOK' => 'kr',
	);
	return isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : '$';
}

function rcp_has_paid_levels() {
	return (bool) rcp_get_membership_levels( array( 'price__not_in' => array( 0 ), 'status' => 'active', 'number' => 1 ) );
}

function rcp_get_paid_levels() {
	$paid_levels = rcp_get_membership_levels( array(
		'price__not_in' => array( 0 ),
		'status'        => 'active',
		'number'        => 9999,
	) );
	return apply_filters( 'rcp_get_paid_levels', $paid_levels );
}

function rcp_get_trial_level_ids() {
	$ids = array();
	foreach ( rcp_get_membership_levels( array( 'number' => 999 ) ) as $level ) {
		if ( $level->has_trial() ) {
			$ids[] = $level->get_id();
		}
	}
	return $ids;
}

function rcp_get_template_part( $slug, $name = null, $load = true ) {
	$template = '';
	if ( $name ) {
		$template = locate_template( array( "{$slug}-{$name}.php", "{$slug}.php" ) );
	}
	if ( empty( $template ) ) {
		$template = SMM_PLUGIN_DIR . 'includes/templates/' . $slug . '.php';
		if ( ! file_exists( $template ) ) {
			$template = '';
		}
	}
	if ( $load && ! empty( $template ) ) {
		load_template( $template, false );
	}
	return $template;
}

function _rcp_sanitize_duration_unit( $unit ) {
	return in_array( $unit, array( 'day', 'month', 'year' ), true ) ? $unit : 'day';
}

/* --------------------------------------------------------------------- *
 * Status Transition Hook (bridges rcp_transition_membership_status
 * to rcp_transition_membership_status_{new_status})
 * --------------------------------------------------------------------- */

function rcp_transition_membership_status_hook( $old_status, $new_status, $membership_id ) {
	do_action( 'rcp_transition_membership_status_' . sanitize_key( $new_status ), $old_status, $membership_id );
}
add_action( 'rcp_transition_membership_status', 'rcp_transition_membership_status_hook', 10, 3 );

function rcp_update_expired_membership_role( $old_status, $membership_id ) {
	$membership = rcp_get_membership( $membership_id );
	if ( ! $membership ) {
		return;
	}
	$membership_level = rcp_get_membership_level( $membership->get_object_id() );
	$default_role = get_option( 'default_role', 'subscriber' );
	if ( $membership_level instanceof Membership_Level && $membership_level->get_role() !== $default_role && 'administrator' !== $membership_level->get_role() ) {
		$user = get_userdata( $membership->get_user_id() );
		if ( $user ) {
			$user->remove_role( $membership_level->get_role() );
		}
	}
}
add_action( 'rcp_transition_membership_status_expired', 'rcp_update_expired_membership_role', 10, 2 );

/* --------------------------------------------------------------------- *
 * Table name compat functions
 * --------------------------------------------------------------------- */

function rcp_get_levels_db_name() {
	global $wpdb;
	return $wpdb->prefix . 'restrict_content_pro';
}

function rcp_get_level_meta_db_name() {
	global $wpdb;
	return $wpdb->prefix . 'rcp_subscription_level_meta';
}

function rcp_get_memberships_db_name() {
	global $wpdb;
	return $wpdb->prefix . 'rcp_memberships';
}

function rcp_get_customers_db_name() {
	global $wpdb;
	return $wpdb->prefix . 'rcp_customers';
}

function rcp_get_payments_db_name() {
	global $wpdb;
	return $wpdb->prefix . 'rcp_payments';
}

function rcp_get_payment_meta_db_name() {
	global $wpdb;
	return $wpdb->prefix . 'rcp_payment_meta';
}

function rcp_get_discounts_db_name() {
	global $wpdb;
	return $wpdb->prefix . 'rcp_discounts';
}

/* --------------------------------------------------------------------- *
 * Capabilities
 * --------------------------------------------------------------------- */

function rcp_view_members() {
	return current_user_can( 'manage_options' );
}

function rcp_manage_members() {
	return current_user_can( 'manage_options' );
}

function rcp_view_levels() {
	return current_user_can( 'manage_options' );
}

function rcp_view_payments() {
	return current_user_can( 'manage_options' );
}

function rcp_manage_settings() {
	return current_user_can( 'manage_options' );
}

/* --------------------------------------------------------------------- *
 * Admin page URL helpers
 * --------------------------------------------------------------------- */

function rcp_get_memberships_admin_page( $args = array() ) {
	$args = wp_parse_args( $args, array( 'page' => 'rcp-members' ) );
	return add_query_arg( $args, admin_url( 'admin.php' ) );
}

function rcp_get_customers_admin_page( $args = array() ) {
	$args = wp_parse_args( $args, array( 'page' => 'rcp-customers' ) );
	return add_query_arg( $args, admin_url( 'admin.php' ) );
}

function rcp_get_levels_admin_page( $args = array() ) {
	$args = wp_parse_args( $args, array( 'page' => 'rcp-member-levels' ) );
	return add_query_arg( $args, admin_url( 'admin.php' ) );
}

/* --------------------------------------------------------------------- *
 * Payment gateway stubs (no payment processing)
 * --------------------------------------------------------------------- */

function rcp_get_payment_gateways() {
	return array(
		'manual' => array( 'admin_label' => __( 'Manual', 'rcp' ), 'checkout_label' => __( 'Manual', 'rcp' ) ),
	);
}

function rcp_get_gateway_slug_from_gateway_ids( $args ) {
	return '';
}

function rcp_gateway_supports( $gateway, $feature ) {
	return false;
}

function rcp_get_gateway_class( $gateway ) {
	return false;
}

function rcp_get_auto_renew_behavior() {
	global $rcp_options;
	return isset( $rcp_options['auto_renew'] ) ? $rcp_options['auto_renew'] : '2';
}

function rcp_get_registration_page_url() {
	global $rcp_options;
	if ( ! empty( $rcp_options['registration_page'] ) ) {
		return get_permalink( $rcp_options['registration_page'] );
	}
	return home_url();
}

function rcp_count_members( $level_id, $status = 'active' ) {
	return smm_db_count_members_by_level( $level_id, $status );
}

function rcp_show_subscription_level( $level_id = 0, $user_id = 0 ) {
	return true;
}
