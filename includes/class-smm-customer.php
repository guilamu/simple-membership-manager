<?php
/**
 * Customer Compat Class
 *
 * Registered as RCP_Customer for backwards compatibility.
 *
 * @package Simple_Membership_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

#[\AllowDynamicProperties]
class RCP_Customer {

	protected $id = 0;
	protected $user_id = 0;
	protected $date_registered = '';
	protected $email_verification = 'none';
	protected $last_login = '';
	protected $has_trialed = null;
	protected $ips = '';
	protected $notes = '';
	protected $member = null;

	public function __construct( $customer_object = null ) {
		if ( ! is_object( $customer_object ) ) {
			return;
		}
		$this->setup_customer( $customer_object );
	}

	private function setup_customer( $customer_object ) {
		if ( ! is_object( $customer_object ) ) {
			return false;
		}
		$vars = get_object_vars( $customer_object );
		foreach ( $vars as $key => $value ) {
			switch ( $key ) {
				case 'date_registered':
				case 'last_login':
					if ( '0000-00-00 00:00:00' === $value || is_null( $value ) ) {
						$value = '';
					}
					break;
				case 'ips':
					$value = maybe_unserialize( $value );
					break;
			}
			$this->{$key} = $value;
		}
		return ! empty( $this->id );
	}

	public function update( $data = array() ) {
		if ( ! empty( $data['ips'] ) && is_array( $data['ips'] ) ) {
			$data['ips'] = maybe_serialize( $data['ips'] );
		}
		if ( ! empty( $data['email_verification'] ) && ! in_array( $data['email_verification'], array( 'none', 'pending', 'verified' ), true ) ) {
			unset( $data['email_verification'] );
		}
		$updated = smm_db_update_customer( $this->get_id(), $data );
		if ( $updated ) {
			foreach ( $data as $key => $value ) {
				if ( 'ips' === $key ) {
					$this->{$key} = maybe_unserialize( $value );
				} else {
					$this->{$key} = $value;
				}
			}
			return true;
		}
		return false;
	}

	public function get_id() {
		return (int) $this->id;
	}

	public function get_user_id() {
		return (int) $this->user_id;
	}

	public function get_member() {
		if ( ! is_object( $this->member ) ) {
			$this->member = new RCP_Member( $this->get_user_id() );
		}
		return $this->member;
	}

	public function get_date_registered( $formatted = true ) {
		$date_registered = $this->date_registered;
		if ( $formatted && ! empty( $date_registered ) ) {
			$date_registered = date_i18n( get_option( 'date_format' ), strtotime( $date_registered, current_time( 'timestamp' ) ) );
		}
		return $date_registered;
	}

	public function get_email_verification_status() {
		return $this->email_verification;
	}

	public function get_last_login( $formatted = true ) {
		$last_login = $this->last_login;
		if ( $formatted && ! empty( $last_login ) ) {
			$last_login = date_i18n( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), strtotime( $last_login, current_time( 'timestamp' ) ) );
		}
		return $last_login;
	}

	public function get_ips() {
		$ips = is_array( $this->ips ) ? $this->ips : array();
		return $ips;
	}

	public function add_ip( $ip ) {
		$ips = $this->get_ips();
		if ( ! in_array( $ip, $ips, true ) ) {
			$ips[] = sanitize_text_field( $ip );
		}
		$this->update( array( 'ips' => $ips ) );
	}

	public function has_trialed() {
		if ( ! is_null( $this->has_trialed ) ) {
			$has_trialed = (bool) $this->has_trialed;
		} else {
			$memberships = $this->get_memberships( array(
				'status__not_in' => array( 'pending' ),
				'disabled'       => '',
			) );
			$has_trialed = false;
			if ( is_array( $memberships ) ) {
				foreach ( $memberships as $membership ) {
					if ( $membership->get_trial_end_date() ) {
						$has_trialed = true;
						break;
					}
				}
			}
			$this->update( array( 'has_trialed' => $has_trialed ? 1 : 0 ) );
		}
		return apply_filters( 'rcp_customer_has_trialed', $has_trialed, $this->get_id(), $this );
	}

	public function get_notes() {
		return apply_filters( 'rcp_customer_get_notes', $this->notes, $this->get_id(), $this );
	}

	public function add_note( $note = '' ) {
		$notes = $this->get_notes();
		if ( empty( $notes ) ) {
			$notes = '';
		}
		$notes .= "\n\n" . date_i18n( 'F j, Y H:i:s', current_time( 'timestamp' ) ) . ' - ' . $note;
		$this->update( array( 'notes' => $notes ) );
		do_action( 'rcp_customer_add_note', $note, $this->get_id(), $this );
		return true;
	}

	public function is_pending_verification() {
		$is_pending = 'pending' === $this->email_verification;
		return apply_filters( 'rcp_is_pending_email_verification', $is_pending, $this->get_user_id(), $this->get_member() );
	}

	public function verify_email() {
		$updated = $this->update( array( 'email_verification' => 'verified' ) );
		if ( $updated ) {
			$this->add_note( __( 'Email successfully verified.', 'rcp' ) );
		}
		do_action( 'rcp_customer_post_verify_email', $this->get_id(), $this );
		return $updated;
	}

	public function get_memberships( $args = array() ) {
		if ( 0 === $this->get_id() ) {
			return array();
		}
		$defaults = array(
			'customer_id' => $this->get_id(),
			'number'      => 9999,
		);
		$args = wp_parse_args( $args, $defaults );
		return rcp_get_memberships( $args );
	}

	public function has_active_membership() {
		$memberships = $this->get_memberships( array(
			'status' => array( 'active', 'cancelled' ),
		) );
		if ( empty( $memberships ) ) {
			return false;
		}
		foreach ( $memberships as $membership ) {
			if ( $membership->is_active() ) {
				return true;
			}
		}
		return false;
	}

	public function has_paid_membership( $include_trial = true ) {
		$memberships = $this->get_memberships( array(
			'status' => array( 'active', 'cancelled' ),
		) );
		if ( empty( $memberships ) ) {
			return false;
		}
		foreach ( $memberships as $membership ) {
			if ( $membership->is_active() && $membership->is_paid( $include_trial ) ) {
				return true;
			}
		}
		return false;
	}

	public function add_membership( $args = array() ) {
		$args['customer_id'] = $this->get_id();
		$args['user_id']     = $this->get_user_id();
		return rcp_add_membership( $args );
	}

	public function disable_memberships( $exclude = 0 ) {
		$memberships = $this->get_memberships();
		if ( empty( $memberships ) ) {
			return;
		}
		if ( ! empty( $exclude ) && ! is_array( $exclude ) ) {
			$exclude = array( $exclude );
		}
		foreach ( $memberships as $membership ) {
			if ( ! empty( $exclude ) && in_array( $membership->get_id(), $exclude, true ) ) {
				continue;
			}
			$membership->disable();
		}
	}

	public function can_access( $post_id = 0 ) {
		if ( user_can( $this->user_id, 'manage_options' ) ) {
			return apply_filters( 'rcp_member_can_access', true, $this->get_user_id(), $post_id, $this->get_member() );
		}
		if ( ! rcp_is_restricted_content( $post_id ) ) {
			return apply_filters( 'rcp_member_can_access', true, $this->get_user_id(), $post_id, $this->get_member() );
		}
		if ( empty( $this->id ) ) {
			return apply_filters( 'rcp_member_can_access', false, $this->get_user_id(), $post_id, $this->get_member() );
		}
		if ( $this->is_pending_verification() ) {
			return apply_filters( 'rcp_member_can_access', false, $this->get_user_id(), $post_id, $this->get_member() );
		}
		$can_access = false;
		foreach ( $this->get_memberships() as $membership ) {
			if ( $membership->can_access( $post_id ) ) {
				$can_access = true;
				break;
			}
		}
		return apply_filters( 'rcp_member_can_access', $can_access, $this->get_user_id(), $post_id, $this->get_member() );
	}

	public function has_access_level( $access_level_needed = 0 ) {
		$memberships = $this->get_memberships( array(
			'status' => array( 'active', 'cancelled' ),
		) );
		if ( empty( $memberships ) ) {
			return 0 === $access_level_needed;
		}
		foreach ( $memberships as $membership ) {
			if ( $membership->has_access_level( $access_level_needed ) ) {
				return true;
			}
		}
		return 0 === $access_level_needed;
	}
}

/**
 * Deprecated RCP_Member class for backwards compatibility.
 *
 * Extends WP_User and delegates to RCP_Customer / RCP_Membership.
 */
class RCP_Member extends WP_User {

	private $customer = false;
	private $membership = false;

	private function get_customer( $create = false ) {
		if ( ! is_object( $this->customer ) ) {
			$this->customer = rcp_get_customer_by_user_id( $this->ID );
			if ( empty( $this->customer ) && ! empty( $create ) ) {
				$customer_id = rcp_add_customer( array( 'user_id' => $this->ID ) );
				if ( ! empty( $customer_id ) ) {
					$this->customer = rcp_get_customer( $customer_id );
				}
			}
		}
		return $this->customer;
	}

	private function get_membership( $create = false ) {
		if ( ! is_object( $this->membership ) ) {
			$customer = $this->get_customer( $create );
			if ( is_object( $customer ) ) {
				$this->membership = rcp_get_customer_single_membership( $customer->get_id() );
				if ( empty( $this->membership ) && ! empty( $create ) ) {
					$membership_id = rcp_add_membership( array( 'customer_id' => $customer->get_id() ) );
					if ( ! empty( $membership_id ) ) {
						$this->membership = rcp_get_membership( $membership_id );
					}
				}
			}
		}
		return $this->membership;
	}

	public function get_status() {
		$membership = $this->get_membership();
		if ( ! empty( $membership ) ) {
			if ( ! $membership->is_paid() && $membership->is_active() ) {
				return 'free';
			}
			return $membership->get_status();
		}
		return false;
	}

	public function is_expired() {
		$membership = $this->get_membership();
		return ! empty( $membership ) ? $membership->is_expired() : false;
	}

	public function get_expiration_date() {
		$membership = $this->get_membership();
		return ! empty( $membership ) ? $membership->get_expiration_date() : false;
	}

	public function get_notes() {
		$customer = $this->get_customer();
		return ! empty( $customer ) ? $customer->get_notes() : '';
	}

	public function add_note( $note = '' ) {
		$customer = $this->get_customer( true );
		if ( $customer ) {
			return $customer->add_note( $note );
		}
		return false;
	}

	public function is_active() {
		$membership = $this->get_membership();
		return ! empty( $membership ) ? $membership->is_active() : false;
	}

	public function is_recurring() {
		$membership = $this->get_membership();
		return ! empty( $membership ) ? $membership->is_recurring() : false;
	}

	public function is_trialing() {
		$membership = $this->get_membership();
		return ! empty( $membership ) ? $membership->is_trialing() : false;
	}

	public function is_paid() {
		$membership = $this->get_membership();
		return ! empty( $membership ) ? $membership->is_paid() : false;
	}

	public function can_access( $post_id = 0 ) {
		$customer = $this->get_customer();
		if ( $customer ) {
			return $customer->can_access( $post_id );
		}
		if ( user_can( $this->ID, 'manage_options' ) ) {
			return true;
		}
		return ! rcp_is_restricted_content( $post_id );
	}
}
