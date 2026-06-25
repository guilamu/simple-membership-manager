<?php
/**
 * Membership Level Compat Class
 *
 * Registered as RCP\Membership_Level for backwards compatibility.
 *
 * @package Simple_Membership_Manager
 */

namespace RCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Membership_Level {

	protected $id = 0;
	protected $name = '';
	protected $description = '';
	protected $duration = 0;
	protected $duration_unit = 'month';
	protected $trial_duration = 0;
	protected $trial_duration_unit = 'day';
	protected $price = '0';
	protected $fee = '0';
	protected $maximum_renewals = 0;
	protected $after_final_payment = '';
	protected $list_order = 0;
	protected $level = 0;
	protected $status = 'inactive';
	protected $role = 'subscriber';
	protected $date_created = '';
	protected $date_modified = '';

	public function __construct( $level_object = null ) {
		if ( ! is_object( $level_object ) ) {
			return;
		}
		$this->setup( $level_object );
	}

	private function setup( $level_object ) {
		if ( ! is_object( $level_object ) ) {
			return false;
		}
		$vars = get_object_vars( $level_object );
		foreach ( $vars as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				$this->{$key} = $value;
			}
		}
		return ! empty( $this->id );
	}

	public function __isset( $key ) {
		if ( property_exists( $this, $key ) ) {
			return false === empty( $this->{$key} );
		}
		return false;
	}

	public function __get( $key ) {
		$key = sanitize_key( $key );
		if ( method_exists( $this, 'get_' . $key ) ) {
			return call_user_func( array( $this, 'get_' . $key ) );
		} elseif ( property_exists( $this, $key ) ) {
			return $this->{$key};
		}
		return new \WP_Error( 'invalid-property', sprintf( __( 'Can\'t get property %s', 'rcp' ), $key ) );
	}

	public function get_id() {
		return absint( $this->id );
	}

	public function get_name() {
		return $this->name;
	}

	public function get_description() {
		return apply_filters( 'rcp_get_subscription_description', $this->description, $this->get_id() );
	}

	public function get_duration() {
		return absint( $this->duration );
	}

	public function get_duration_unit() {
		return $this->duration_unit;
	}

	public function is_lifetime() {
		return 0 === $this->get_duration();
	}

	public function get_trial_duration() {
		return absint( $this->trial_duration );
	}

	public function get_trial_duration_unit() {
		return $this->trial_duration_unit;
	}

	public function has_trial() {
		return $this->get_trial_duration() > 0;
	}

	public function get_price() {
		return apply_filters( 'rcp_membership_level_price', floatval( $this->price ), $this );
	}

	public function is_free() {
		return 0 == $this->get_price();
	}

	public function get_fee() {
		return floatval( $this->fee );
	}

	public function get_maximum_renewals() {
		return absint( $this->maximum_renewals );
	}

	public function get_after_final_payment() {
		return $this->after_final_payment;
	}

	public function get_list_order() {
		return intval( $this->list_order );
	}

	public function get_access_level() {
		return absint( $this->level );
	}

	public function get_status() {
		return $this->status;
	}

	public function get_role() {
		return ! empty( $this->role ) ? $this->role : 'subscriber';
	}

	public function get_date_created() {
		return $this->date_created;
	}

	public function get_date_modified() {
		return $this->date_modified;
	}

	public function export_vars() {
		return array(
			'name'                => $this->name,
			'description'         => $this->description,
			'duration'            => $this->duration,
			'duration_unit'       => $this->duration_unit,
			'trial_duration'      => $this->trial_duration,
			'trial_duration_unit' => $this->trial_duration_unit,
			'price'               => $this->price,
			'fee'                 => $this->fee,
			'maximum_renewals'    => $this->maximum_renewals,
			'after_final_payment' => $this->after_final_payment,
			'list_order'          => $this->list_order,
			'level'               => $this->level,
			'status'              => $this->status,
			'role'                => $this->role,
		);
	}
}
