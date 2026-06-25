<?php
/**
 * Database Layer
 *
 * Direct $wpdb queries for all RCP database tables.
 *
 * @package Simple_Membership_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the membership levels table name.
 *
 * @return string
 */
function smm_db_levels_table() {
	global $wpdb;
	return $wpdb->prefix . 'restrict_content_pro';
}

/**
 * Get the membership level meta table name.
 *
 * @return string
 */
function smm_db_level_meta_table() {
	global $wpdb;
	return $wpdb->prefix . 'rcp_subscription_level_meta';
}

/**
 * Get the customers table name.
 *
 * @return string
 */
function smm_db_customers_table() {
	global $wpdb;
	return $wpdb->prefix . 'rcp_customers';
}

/**
 * Get the memberships table name.
 *
 * @return string
 */
function smm_db_memberships_table() {
	global $wpdb;
	return $wpdb->prefix . 'rcp_memberships';
}

/**
 * Get the membership meta table name.
 *
 * @return string
 */
function smm_db_membership_meta_table() {
	global $wpdb;
	return $wpdb->prefix . 'rcp_membershipmeta';
}

/* --------------------------------------------------------------------- *
 * Membership Level DB functions
 * --------------------------------------------------------------------- */

function smm_db_get_membership_level( $level_id ) {
	global $wpdb;
	$table = smm_db_levels_table();
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $level_id ) ) );
	return $row ? $row : false;
}

function smm_db_get_membership_level_by( $field, $value ) {
	global $wpdb;
	$table   = smm_db_levels_table();
	$allowed = array( 'id', 'name', 'status', 'role', 'level', 'list_order' );
	if ( ! in_array( $field, $allowed, true ) ) {
		return false;
	}
	if ( 'name' === $field ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE name = %s LIMIT 1", $value ) );
	} else {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$field} = %d LIMIT 1", absint( $value ) ) );
	}
	return $row ? $row : false;
}

function smm_db_get_membership_levels( $args = array() ) {
	global $wpdb;
	$table = smm_db_levels_table();

	$defaults = array(
		'number'   => 20,
		'offset'   => 0,
		'orderby'  => 'list_order',
		'order'    => 'ASC',
		'status'   => '',
		'id__in'   => array(),
		'id__not_in' => array(),
		'search'   => '',
		'count'    => false,
	);
	$args     = wp_parse_args( $args, $defaults );

	$sortable = array( 'id', 'name', 'duration', 'trial_duration', 'maximum_renewals', 'list_order', 'level', 'status', 'date_created', 'date_modified' );
	$orderby  = in_array( $args['orderby'], $sortable, true ) ? $args['orderby'] : 'list_order';
	$order    = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

	$where = array( '1=1' );

	if ( ! empty( $args['status'] ) ) {
		if ( is_array( $args['status'] ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $args['status'] ), '%s' ) );
			$where[]      = $wpdb->prepare( "status IN ($placeholders)", $args['status'] );
		} else {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}
	}

	if ( ! empty( $args['id__in'] ) ) {
		$id__in      = array_map( 'absint', (array) $args['id__in'] );
		$placeholders = implode( ',', array_fill( 0, count( $id__in ), '%d' ) );
		$where[]      = $wpdb->prepare( "id IN ($placeholders)", $id__in );
	}

	if ( ! empty( $args['id__not_in'] ) ) {
		$id__not_in   = array_map( 'absint', (array) $args['id__not_in'] );
		$placeholders = implode( ',', array_fill( 0, count( $id__not_in ), '%d' ) );
		$where[]      = $wpdb->prepare( "id NOT IN ($placeholders)", $id__not_in );
	}

	if ( ! empty( $args['search'] ) ) {
		$where[] = $wpdb->prepare( '(name LIKE %s OR description LIKE %s)', '%' . $wpdb->esc_like( $args['search'] ) . '%', '%' . $wpdb->esc_like( $args['search'] ) . '%' );
	}

	if ( isset( $args['price__not_in'] ) && is_array( $args['price__not_in'] ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $args['price__not_in'] ), '%s' ) );
		$where[]      = $wpdb->prepare( "price NOT IN ($placeholders)", $args['price__not_in'] );
	}

	$where_clause = implode( ' AND ', $where );

	if ( ! empty( $args['count'] ) ) {
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}" );
	}

	$number  = absint( $args['number'] );
	$offset  = absint( $args['offset'] );
	$limit   = $number > 0 ? "LIMIT {$offset}, {$number}" : '';

	if ( ! empty( $args['fields'] ) && 'id' === $args['fields'] ) {
		return $wpdb->get_col( "SELECT id FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} {$limit}" );
	}

	return $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} {$limit}" );
}

function smm_db_insert_membership_level( $data ) {
	global $wpdb;
	$table = smm_db_levels_table();

	$defaults = array(
		'name'                => '',
		'description'         => '',
		'duration'            => 0,
		'duration_unit'       => 'month',
		'trial_duration'      => 0,
		'trial_duration_unit' => 'day',
		'price'               => '0',
		'fee'                 => '0',
		'maximum_renewals'    => 0,
		'after_final_payment' => '',
		'list_order'          => 0,
		'level'               => 0,
		'status'              => 'inactive',
		'role'                => 'subscriber',
		'date_created'        => current_time( 'mysql' ),
		'date_modified'       => current_time( 'mysql' ),
	);
	$data     = wp_parse_args( $data, $defaults );

	$inserted = $wpdb->insert( $table, $data );
	return $inserted ? (int) $wpdb->insert_id : false;
}

function smm_db_update_membership_level( $level_id, $data ) {
	global $wpdb;
	$table = smm_db_levels_table();

	$data['date_modified'] = current_time( 'mysql' );

	$updated = $wpdb->update( $table, $data, array( 'id' => absint( $level_id ) ) );
	return $updated !== false;
}

function smm_db_delete_membership_level( $level_id ) {
	global $wpdb;
	$table = smm_db_levels_table();
	return $wpdb->delete( $table, array( 'id' => absint( $level_id ) ) ) !== false;
}

/* --------------------------------------------------------------------- *
 * Level Meta DB functions
 * --------------------------------------------------------------------- */

function smm_db_get_membership_level_meta( $level_id, $key = '', $single = false ) {
	global $wpdb;
	$table = smm_db_level_meta_table();

	if ( ! empty( $key ) ) {
		if ( $single ) {
			$meta = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$table} WHERE level_id = %d AND meta_key = %s LIMIT 1", absint( $level_id ), $key ) );
			return $meta ? maybe_unserialize( $meta ) : '';
		}
		$metas = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$table} WHERE level_id = %d AND meta_key = %s", absint( $level_id ), $key ) );
		return array_map( 'maybe_unserialize', $metas );
	}

	$metas = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$table} WHERE level_id = %d", absint( $level_id ) ) );
	$result = array();
	foreach ( $metas as $meta ) {
		if ( isset( $result[ $meta->meta_key ] ) ) {
			$result[ $meta->meta_key ] = array( $result[ $meta->meta_key ] );
			$result[ $meta->meta_key ][] = maybe_unserialize( $meta->meta_value );
		} else {
			$result[ $meta->meta_key ] = maybe_unserialize( $meta->meta_value );
		}
	}
	return $result;
}

function smm_db_update_membership_level_meta( $level_id, $key, $value ) {
	global $wpdb;
	$table = smm_db_level_meta_table();

	$existing = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$table} WHERE level_id = %d AND meta_key = %s LIMIT 1", absint( $level_id ), $key ) );
	$value    = maybe_serialize( $value );

	if ( $existing ) {
		return $wpdb->update( $table, array( 'meta_value' => $value ), array( 'meta_id' => $existing ) ) !== false;
	}
	return $wpdb->insert( $table, array( 'level_id' => absint( $level_id ), 'meta_key' => $key, 'meta_value' => $value ) ) !== false;
}

function smm_db_delete_membership_level_meta( $level_id, $key ) {
	global $wpdb;
	$table = smm_db_level_meta_table();
	return $wpdb->delete( $table, array( 'level_id' => absint( $level_id ), 'meta_key' => $key ) ) !== false;
}

/* --------------------------------------------------------------------- *
 * Customer DB functions
 * --------------------------------------------------------------------- */

function smm_db_get_customer( $customer_id ) {
	global $wpdb;
	$table = smm_db_customers_table();
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $customer_id ) ) );
	return $row ? $row : false;
}

function smm_db_get_customer_by( $field, $value ) {
	global $wpdb;
	$table   = smm_db_customers_table();
	$allowed = array( 'id', 'user_id' );
	if ( ! in_array( $field, $allowed, true ) ) {
		return false;
	}
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$field} = %d LIMIT 1", absint( $value ) ) );
	return $row ? $row : false;
}

function smm_db_get_customers( $args = array() ) {
	global $wpdb;
	$table = smm_db_customers_table();

	$defaults = array(
		'number'   => 20,
		'offset'   => 0,
		'orderby'  => 'id',
		'order'    => 'DESC',
		'search'   => '',
		'count'    => false,
		'id__in'   => array(),
		'id__not_in' => array(),
	);
	$args     = wp_parse_args( $args, $defaults );

	$sortable = array( 'id', 'user_id', 'date_registered', 'last_login' );
	$orderby  = in_array( $args['orderby'], $sortable, true ) ? $args['orderby'] : 'id';
	$order    = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

	$where = array( '1=1' );

	if ( ! empty( $args['id__in'] ) ) {
		$id__in       = array_map( 'absint', (array) $args['id__in'] );
		$placeholders = implode( ',', array_fill( 0, count( $id__in ), '%d' ) );
		$where[]      = $wpdb->prepare( "id IN ($placeholders)", $id__in );
	}
	if ( ! empty( $args['id__not_in'] ) ) {
		$id__not_in   = array_map( 'absint', (array) $args['id__not_in'] );
		$placeholders = implode( ',', array_fill( 0, count( $id__not_in ), '%d' ) );
		$where[]      = $wpdb->prepare( "id NOT IN ($placeholders)", $id__not_in );
	}
	if ( ! empty( $args['search'] ) ) {
		$where[] = $wpdb->prepare( '(notes LIKE %s)', '%' . $wpdb->esc_like( $args['search'] ) . '%' );
	}

	$where_clause = implode( ' AND ', $where );

	if ( ! empty( $args['count'] ) ) {
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}" );
	}

	$number = absint( $args['number'] );
	$offset = absint( $args['offset'] );
	$limit  = $number > 0 ? "LIMIT {$offset}, {$number}" : '';

	return $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} {$limit}" );
}

function smm_db_insert_customer( $data ) {
	global $wpdb;
	$table = smm_db_customers_table();

	$defaults = array(
		'user_id'            => 0,
		'date_registered'    => current_time( 'mysql' ),
		'email_verification' => 'none',
		'last_login'         => null,
		'has_trialed'        => 0,
		'ips'                => '',
		'notes'              => '',
	);
	$data     = wp_parse_args( $data, $defaults );

	if ( ! empty( $data['ips'] ) && is_array( $data['ips'] ) ) {
		$data['ips'] = maybe_serialize( $data['ips'] );
	}
	if ( ! empty( $data['email_verification'] ) && ! in_array( $data['email_verification'], array( 'none', 'pending', 'verified' ), true ) ) {
		$data['email_verification'] = 'none';
	}

	$inserted = $wpdb->insert( $table, $data );
	return $inserted ? (int) $wpdb->insert_id : false;
}

function smm_db_update_customer( $customer_id, $data ) {
	global $wpdb;
	$table = smm_db_customers_table();

	if ( ! empty( $data['ips'] ) && is_array( $data['ips'] ) ) {
		$data['ips'] = maybe_serialize( $data['ips'] );
	}
	if ( ! empty( $data['email_verification'] ) && ! in_array( $data['email_verification'], array( 'none', 'pending', 'verified' ), true ) ) {
		unset( $data['email_verification'] );
	}

	$updated = $wpdb->update( $table, $data, array( 'id' => absint( $customer_id ) ) );
	return $updated !== false;
}

function smm_db_delete_customer( $customer_id ) {
	global $wpdb;
	$table = smm_db_customers_table();
	return $wpdb->delete( $table, array( 'id' => absint( $customer_id ) ) ) !== false;
}

/* --------------------------------------------------------------------- *
 * Membership DB functions
 * --------------------------------------------------------------------- */

function smm_db_get_membership( $membership_id ) {
	global $wpdb;
	$table = smm_db_memberships_table();
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $membership_id ) ) );
	return $row ? $row : false;
}

function smm_db_get_memberships( $args = array() ) {
	global $wpdb;
	$table = smm_db_memberships_table();

	$defaults = array(
		'number'   => 20,
		'offset'   => 0,
		'orderby'  => 'id',
		'order'    => 'DESC',
		'count'    => false,
		'disabled' => 0,
		'fields'   => '',
	);
	$args     = wp_parse_args( $args, $defaults );

	$sortable = array( 'id', 'object_id', 'object_type', 'currency', 'initial_amount', 'recurring_amount', 'created_date', 'activated_date', 'trial_end_date', 'renewed_date', 'cancellation_date', 'expiration_date', 'times_billed', 'maximum_renewals', 'status', 'gateway_customer_id', 'gateway_subscription_id', 'subscription_key', 'date_modified' );
	$orderby  = in_array( $args['orderby'], $sortable, true ) ? $args['orderby'] : 'id';
	$order    = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

	$where = array( '1=1' );

	if ( '' !== $args['disabled'] ) {
		$where[] = $wpdb->prepare( 'disabled = %d', absint( $args['disabled'] ) );
	}

	$simple_filters = array( 'id', 'customer_id', 'user_id', 'object_id', 'object_type', 'currency', 'status', 'gateway', 'subscription_key', 'upgraded_from', 'auto_renew' );
	foreach ( $simple_filters as $filter ) {
		if ( isset( $args[ $filter ] ) && '' !== $args[ $filter ] ) {
			$val = $args[ $filter ];
			if ( is_array( $val ) ) {
				$int_filters = array( 'id', 'customer_id', 'user_id', 'object_id', 'upgraded_from', 'auto_renew' );
				$placeholder = in_array( $filter, $int_filters, true ) ? '%d' : '%s';
				$placeholders = implode( ',', array_fill( 0, count( $val ), $placeholder ) );
				$where[]      = $wpdb->prepare( "{$filter} IN ($placeholders)", $val );
			} else {
				$int_filters = array( 'id', 'customer_id', 'user_id', 'object_id', 'upgraded_from', 'auto_renew' );
				if ( in_array( $filter, $int_filters, true ) ) {
					$where[] = $wpdb->prepare( "{$filter} = %d", absint( $val ) );
				} else {
					$where[] = $wpdb->prepare( "{$filter} = %s", $val );
				}
			}
		}
	}

	if ( ! empty( $args['status__in'] ) && is_array( $args['status__in'] ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $args['status__in'] ), '%s' ) );
		$where[]      = $wpdb->prepare( "status IN ($placeholders)", $args['status__in'] );
	}
	if ( ! empty( $args['status__not_in'] ) && is_array( $args['status__not_in'] ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $args['status__not_in'] ), '%s' ) );
		$where[]      = $wpdb->prepare( "status NOT IN ($placeholders)", $args['status__not_in'] );
	}
	if ( ! empty( $args['object_id__in'] ) && is_array( $args['object_id__in'] ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $args['object_id__in'] ), '%d' ) );
		$where[]      = $wpdb->prepare( "object_id IN ($placeholders)", array_map( 'absint', $args['object_id__in'] ) );
	}
	if ( ! empty( $args['object_id__not_in'] ) && is_array( $args['object_id__not_in'] ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $args['object_id__not_in'] ), '%d' ) );
		$where[]      = $wpdb->prepare( "object_id NOT IN ($placeholders)", array_map( 'absint', $args['object_id__not_in'] ) );
	}

	$where_clause = implode( ' AND ', $where );

	if ( ! empty( $args['count'] ) ) {
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}" );
	}

	$number = absint( $args['number'] );
	$offset = absint( $args['offset'] );
	$limit  = $number > 0 ? "LIMIT {$offset}, {$number}" : '';

	if ( ! empty( $args['fields'] ) && 'id' === $args['fields'] ) {
		return $wpdb->get_col( "SELECT id FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} {$limit}" );
	}

	return $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} {$limit}" );
}

function smm_db_insert_membership( $data ) {
	global $wpdb;
	$table = smm_db_memberships_table();

	$defaults = array(
		'customer_id'      => 0,
		'user_id'          => null,
		'object_id'        => 0,
		'object_type'      => 'membership',
		'currency'         => 'USD',
		'initial_amount'   => '0.00',
		'recurring_amount' => '0.00',
		'created_date'     => current_time( 'mysql' ),
		'activated_date'   => null,
		'trial_end_date'   => null,
		'renewed_date'     => null,
		'cancellation_date' => null,
		'expiration_date'  => null,
		'payment_plan_completed_date' => null,
		'auto_renew'       => 0,
		'times_billed'     => 0,
		'maximum_renewals' => 0,
		'status'           => 'pending',
		'gateway_customer_id' => '',
		'gateway_subscription_id' => '',
		'gateway'          => '',
		'signup_method'    => 'live',
		'subscription_key' => '',
		'notes'            => '',
		'upgraded_from'    => 0,
		'date_modified'    => current_time( 'mysql' ),
		'disabled'         => 0,
	);
	$data     = wp_parse_args( $data, $defaults );

	if ( 'none' === $data['expiration_date'] ) {
		$data['expiration_date'] = null;
	}

	$inserted = $wpdb->insert( $table, $data );
	return $inserted ? (int) $wpdb->insert_id : false;
}

function smm_db_update_membership( $membership_id, $data ) {
	global $wpdb;
	$table = smm_db_memberships_table();

	if ( isset( $data['status'] ) && 'free' === $data['status'] ) {
		$data['status'] = 'active';
	}
	if ( isset( $data['expiration_date'] ) && 'none' === $data['expiration_date'] ) {
		$data['expiration_date'] = null;
	}

	$data['date_modified'] = current_time( 'mysql' );

	$updated = $wpdb->update( $table, $data, array( 'id' => absint( $membership_id ) ) );
	return $updated !== false;
}

function smm_db_delete_membership( $membership_id ) {
	global $wpdb;
	$table = smm_db_memberships_table();
	return $wpdb->delete( $table, array( 'id' => absint( $membership_id ) ) ) !== false;
}

/* --------------------------------------------------------------------- *
 * Membership Meta DB functions
 * --------------------------------------------------------------------- */

function smm_db_get_membership_meta( $membership_id, $key = '', $single = false ) {
	return get_metadata( 'rcp_membership', $membership_id, $key, $single );
}

function smm_db_update_membership_meta( $membership_id, $key, $value ) {
	return update_metadata( 'rcp_membership', $membership_id, $key, $value );
}

function smm_db_delete_membership_meta( $membership_id, $key, $value = '' ) {
	return delete_metadata( 'rcp_membership', $membership_id, $key, $value );
}

function smm_db_add_membership_meta( $membership_id, $key, $value, $unique = false ) {
	return add_metadata( 'rcp_membership', $membership_id, $key, $value, $unique );
}

/* --------------------------------------------------------------------- *
 * Misc DB helpers
 * --------------------------------------------------------------------- */

function smm_db_count_members_by_level( $level_id, $status = 'active' ) {
	global $wpdb;
	$table = smm_db_memberships_table();
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE object_id = %d AND status = %s AND disabled = 0", absint( $level_id ), $status ) );
}
