<?php
/**
 * Membership Compat Class
 *
 * Registered as RCP_Membership for backwards compatibility.
 *
 * @package Simple_Membership_Manager
 */

use RCP\Membership_Level;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

#[\AllowDynamicProperties]
class RCP_Membership {

	protected $id = 0;
	protected $customer_id = 0;
	protected $user_id = null;
	protected $customer = null;
	protected $member = null;
	protected $object_id = 0;
	protected $object_type = 'membership';
	protected $currency = 'USD';
	protected $initial_amount = 0;
	protected $recurring_amount = 0;
	protected $created_date = '';
	protected $activated_date = '';
	protected $trial_end_date = '';
	protected $renewed_date = '';
	protected $cancellation_date = '';
	protected $expiration_date = null;
	protected $payment_plan_completed_date = null;
	protected $times_billed = 1;
	protected $maximum_renewals = 0;
	protected $status = '';
	protected $auto_renew = 0;
	protected $gateway_customer_id = '';
	protected $gateway_subscription_id = '';
	protected $gateway = '';
	protected $signup_method = '';
	protected $subscription_key = '';
	protected $notes = '';
	protected $upgraded_from = 0;
	protected $date_modified = '';
	protected $disabled = 0;

	public function __construct( $membership_object = null ) {
		if ( ! is_object( $membership_object ) ) {
			return;
		}
		$this->setup_membership( $membership_object );
	}

	private function setup_membership( $membership_object ) {
		if ( ! is_object( $membership_object ) ) {
			return false;
		}
		$vars = get_object_vars( $membership_object );
		foreach ( $vars as $key => $value ) {
			switch ( $key ) {
				case 'created_date':
				case 'activated_date':
				case 'trial_end_date':
				case 'renewed_date':
				case 'cancellation_date':
				case 'expiration_date':
				case 'payment_plan_completed_date':
				case 'date_modified':
					if ( '0000-00-00 00:00:00' === $value || is_null( $value ) ) {
						$value = null;
					}
					break;
			}
			$this->{$key} = $value;
		}
		return ! empty( $this->id );
	}

	public function update( $data = array() ) {
		if ( ! empty( $data['status'] ) && 'free' === $data['status'] ) {
			$data['status'] = 'active';
		}
		if ( ! empty( $data['expiration_date'] ) && 'none' === $data['expiration_date'] ) {
			$data['expiration_date'] = null;
		}

		$old_status = $this->get_status();

		$updated = smm_db_update_membership( $this->get_id(), $data );

		if ( $updated ) {
			if ( ! empty( $data['status'] ) && 'active' === $data['status'] ) {
				$this->add_user_role();
			}

			foreach ( $data as $key => $value ) {
				switch ( $key ) {
					case 'created_date':
					case 'trial_end_date':
					case 'renewed_date':
					case 'cancellation_date':
					case 'expiration_date':
					case 'payment_plan_completed_date':
						if ( '0000-00-00 00:00:00' === $value ) {
							$value = null;
						}
						break;
				}
				$this->{$key} = $value;
			}

			if ( ! empty( $data['status'] ) && $old_status !== $data['status'] ) {
				do_action( 'rcp_transition_membership_status', $old_status, $data['status'], $this->get_id() );
			}

			return true;
		}
		return false;
	}

	public function get_id() {
		return (int) $this->id;
	}

	public function get_customer_id() {
		return (int) $this->customer_id;
	}

	public function get_customer() {
		if ( ! is_object( $this->customer ) ) {
			$this->customer = rcp_get_customer( $this->get_customer_id() );
		}
		return $this->customer;
	}

	public function get_user_id() {
		if ( ! is_null( $this->user_id ) ) {
			return absint( $this->user_id );
		}
		$customer = $this->get_customer();
		if ( $customer instanceof RCP_Customer ) {
			$user_id = $customer->get_user_id();
			if ( ! empty( $user_id ) ) {
				$this->update( array( 'user_id' => absint( $user_id ) ) );
			}
		}
		return $this->user_id;
	}

	private function get_member() {
		if ( ! isset( $this->member ) ) {
			$customer = $this->get_customer();
			$this->member = $customer instanceof RCP_Customer ? $customer->get_member() : false;
		}
		return $this->member;
	}

	public function get_object_id() {
		return (int) $this->object_id;
	}

	public function set_object_id( $object_id ) {
		$this->update( array( 'object_id' => absint( $object_id ) ) );
	}

	public function get_object_type() {
		return $this->object_type;
	}

	public function get_membership_level_name() {
		if ( 'membership' !== $this->object_type ) {
			return false;
		}
		$level = rcp_get_membership_level( $this->get_object_id() );
		if ( ! $level instanceof Membership_Level ) {
			return false;
		}
		return $level->get_name();
	}

	public function get_currency() {
		return $this->currency;
	}

	public function get_initial_amount( $formatted = false ) {
		$initial_amount = $this->initial_amount;
		if ( $formatted ) {
			$initial_amount = rcp_currency_filter( $initial_amount );
		}
		return $initial_amount;
	}

	public function get_recurring_amount( $formatted = false ) {
		$recurring_amount = $this->recurring_amount;
		if ( $formatted ) {
			$recurring_amount = rcp_currency_filter( $recurring_amount );
		}
		return $recurring_amount;
	}

	public function get_status() {
		$status = $this->status;
		$status = apply_filters( 'rcp_membership_get_status', $status, $this->get_id(), $this );
		return $status;
	}

	public function set_status( $new_status ) {
		$set = false;
		$old_status = $this->get_status();
		$new_status = apply_filters( 'rcp_set_membership_status_value', $new_status, $old_status, $this->get_id(), $this );

		if ( ! empty( $new_status ) ) {
			$update_data = array( 'status' => $new_status );
			if ( 'cancelled' === $new_status ) {
				$update_data['cancellation_date'] = current_time( 'mysql' );
			}
			$this->update( $update_data );

			if ( 'expired' !== $new_status ) {
				$user_id = $this->get_user_id();
				if ( ! empty( $user_id ) ) {
					delete_user_meta( $user_id, '_rcp_expired_email_sent' );
				}
			}
			if ( 'cancelled' === $new_status ) {
				$this->set_recurring( false );
			}
			$set = true;
		}
		return $set;
	}

	public function get_expiration_date( $formatted = true ) {
		$expiration = $this->expiration_date;
		if ( empty( $expiration ) ) {
			$expiration = 'none';
		} elseif ( $formatted ) {
			$expiration = date_i18n( get_option( 'date_format' ), strtotime( $expiration, current_time( 'timestamp' ) ) );
		}
		$expiration = apply_filters( 'rcp_membership_get_expiration_date', $expiration, $formatted, $this->get_id(), $this );
		return $expiration;
	}

	public function get_expiration_time() {
		$expiration = $this->get_expiration_date( false );
		$timestamp  = ( $expiration && 'none' !== $expiration ) ? strtotime( $expiration, current_time( 'timestamp' ) ) : false;
		$timestamp  = apply_filters( 'rcp_membership_get_expiration_time', $timestamp, $this->get_id(), $this );
		return $timestamp;
	}

	public function set_expiration_date( $new_date = '' ) {
		$ret = false;
		$old_date = $this->get_expiration_date( false );
		if ( empty( $new_date ) || ( ! empty( $old_date ) && $old_date === $new_date ) ) {
			return $ret;
		}
		$updated = $this->update( array( 'expiration_date' => $new_date ) );
		$ret = $updated;
		return $ret;
	}

	public function calculate_expiration( $from_today = false, $trial = false ) {
		$expiration = $this->get_expiration_time();
		if ( ! $from_today && $expiration > current_time( 'timestamp' ) && $this->is_active() ) {
			$base_timestamp = $expiration;
		} else {
			$base_timestamp = current_time( 'timestamp' );
		}
		$membership_level = rcp_get_membership_level( $this->get_object_id() );
		if ( $membership_level instanceof Membership_Level && ! $membership_level->is_lifetime() ) {
			if ( $membership_level->has_trial() && $trial ) {
				$expire_timestamp = strtotime( '+' . $membership_level->get_trial_duration() . ' ' . $membership_level->get_trial_duration_unit() . ' 23:59:59', $base_timestamp );
			} else {
				$expire_timestamp = strtotime( '+' . $membership_level->get_duration() . ' ' . $membership_level->get_duration_unit() . ' 23:59:59', $base_timestamp );
			}
			$extension_days = array( '29', '30', '31' );
			if ( in_array( date( 'j', $expire_timestamp ), $extension_days, true ) && 'month' === $membership_level->get_duration_unit() ) {
				$month = date( 'n', $expire_timestamp );
				if ( $month < 12 ) {
					$month += 1;
					$year  = date( 'Y', $expire_timestamp );
				} else {
					$month = 1;
					$year  = date( 'Y', $expire_timestamp ) + 1;
				}
				$expire_timestamp = mktime( 0, 0, 0, $month, 1, $year );
			}
			$expiration = date( 'Y-m-d 23:59:59', $expire_timestamp );
		} else {
			$expiration = 'none';
		}
		$expiration = apply_filters( 'rcp_membership_calculated_expiration_date', $expiration, $this->get_id(), $this );
		return $expiration;
	}

	public function get_created_date( $formatted = true ) {
		$created_date = $this->created_date;
		if ( $formatted ) {
			$created_date = date_i18n( get_option( 'date_format' ), strtotime( $created_date, current_time( 'timestamp' ) ) );
		}
		return $created_date;
	}

	public function get_activated_date() {
		if ( empty( $this->activated_date ) && $this->is_active() && $this->get_created_date( false ) ) {
			$this->update( array( 'activated_date' => $this->get_created_date( false ) ) );
		}
		return $this->activated_date;
	}

	public function get_trial_end_date() {
		return $this->trial_end_date;
	}

	public function is_trialing() {
		global $rcp_options;
		$membership_level = rcp_get_membership_level( $this->get_object_id() );
		$trial_duration = 0;
		if ( $membership_level instanceof Membership_Level ) {
			$trial_duration = $membership_level->get_trial_duration();
		}
		$free_subs_swap = isset( $rcp_options['disable_trial_free_subs'] ) && (bool) $rcp_options['disable_trial_free_subs'];
		if ( $free_subs_swap ) {
			$is_trialing = false;
		} elseif ( empty( $this->trial_end_date ) ) {
			$is_trialing = false;
		} elseif ( strtotime( $this->trial_end_date, current_time( 'timestamp' ) ) > current_time( 'timestamp' ) && $trial_duration > 0 ) {
			$is_trialing = true;
		} else {
			$is_trialing = false;
		}
		if ( ! $this->is_active() ) {
			$is_trialing = false;
		}
		$is_trialing = apply_filters( 'rcp_membership_is_trialing', $is_trialing, $this->get_id(), $this );
		return $is_trialing;
	}

	public function get_renewed_date( $formatted = true ) {
		$renewed_date = $this->renewed_date;
		if ( $formatted && ! empty( $renewed_date ) ) {
			$renewed_date = date_i18n( get_option( 'date_format' ), strtotime( $renewed_date, current_time( 'timestamp' ) ) );
		}
		return $renewed_date;
	}

	public function set_renewed_date( $date = '' ) {
		if ( empty( $date ) ) {
			$date = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) );
		}
		return $this->update( array( 'renewed_date' => $date ) );
	}

	public function get_cancellation_date( $formatted = true ) {
		$cancellation_date = $this->cancellation_date;
		if ( $formatted && ! empty( $cancellation_date ) ) {
			$cancellation_date = date_i18n( get_option( 'date_format' ), strtotime( $cancellation_date, current_time( 'timestamp' ) ) );
		}
		return $cancellation_date;
	}

	public function get_times_billed() {
		return (int) $this->times_billed;
	}

	public function get_maximum_renewals() {
		return (int) $this->maximum_renewals;
	}

	public function is_active() {
		if ( $this->is_disabled() ) {
			$is_active = false;
		} else {
			if ( $this->is_expired() ) {
				$is_active = false;
			} else {
				$is_active = in_array( $this->get_status(), array( 'active', 'cancelled' ), true );
			}
		}
		$is_active = apply_filters( 'rcp_membership_is_active', $is_active, $this->get_id(), $this );
		return $is_active;
	}

	public function is_paid( $include_trial = true ) {
		if ( $include_trial && $this->is_trialing() ) {
			return true;
		}
		if ( $this->recurring_amount > 0 || $this->initial_amount > 0 ) {
			return true;
		}
		$membership_level = rcp_get_membership_level( $this->get_object_id() );
		if ( $membership_level instanceof Membership_Level && ! $membership_level->is_free() ) {
			return true;
		}
		return false;
	}

	public function is_expired() {
		$is_expired = false;
		$expiration = $this->get_expiration_date( false );
		if ( $expiration && strtotime( 'NOW', current_time( 'timestamp' ) ) > strtotime( $expiration, current_time( 'timestamp' ) ) ) {
			$is_expired = true;
		}
		if ( 'none' === $expiration ) {
			$is_expired = false;
		}
		if ( $is_expired && ! in_array( $this->get_status(), array( 'expired', 'pending' ), true ) ) {
			$this->set_status( 'expired' );
		}
		$is_expired = apply_filters( 'rcp_membership_is_expired', $is_expired, $this->get_id(), $this );
		return $is_expired;
	}

	public function is_recurring() {
		$is_recurring = ! empty( $this->auto_renew );
		$is_recurring = apply_filters( 'rcp_membership_is_recurring', $is_recurring, $this->get_id(), $this );
		return $is_recurring;
	}

	public function set_recurring( $is_recurring = true ) {
		$this->update( array( 'auto_renew' => (int) $is_recurring ) );
	}

	public function get_gateway() {
		return $this->gateway;
	}

	public function get_gateway_customer_id() {
		return $this->gateway_customer_id;
	}

	public function set_gateway_customer_id( $customer_id = '' ) {
		$this->update( array( 'gateway_customer_id' => trim( $customer_id ) ) );
	}

	public function get_gateway_subscription_id() {
		return $this->gateway_subscription_id;
	}

	public function set_gateway_subscription_id( $subscription_id = '' ) {
		$this->update( array( 'gateway_subscription_id' => trim( $subscription_id ) ) );
	}

	public function get_subscription_key() {
		return $this->subscription_key;
	}

	public function get_upgraded_from() {
		return (int) $this->upgraded_from;
	}

	public function was_upgrade() {
		return ! empty( $this->upgraded_from );
	}

	public function was_upgraded() {
		$memberships = rcp_get_memberships( array(
			'upgraded_from' => $this->get_id(),
			'number'        => 1,
			'fields'        => 'id',
			'disabled'      => '',
		) );
		if ( ! empty( $memberships ) ) {
			return reset( $memberships );
		}
		return false;
	}

	public function is_disabled() {
		return ! empty( $this->disabled );
	}

	public function disable() {
		$this->add_note( __( 'Membership disabled.', 'rcp' ) );
		$this->update( array( 'disabled' => 1 ) );
		$membership_level = rcp_get_membership_level( $this->get_object_id() );
		$role = $membership_level instanceof Membership_Level ? $membership_level->get_role() : get_option( 'default_role', 'subscriber' );
		$user_id = $this->get_user_id();
		$user = ! empty( $user_id ) ? new WP_User( $user_id ) : false;
		if ( $user instanceof WP_User && 'administrator' !== $role ) {
			$user->remove_role( $role );
		}
		do_action( 'rcp_membership_post_disable', $this->get_id(), $this );
	}

	public function enable() {
		$this->update( array( 'disabled' => 0 ) );
		if ( $this->is_active() ) {
			$this->add_user_role();
		}
	}

	public function activate() {
		do_action( 'rcp_membership_pre_activate', $this->get_id(), $this );
		$this->add_note( __( 'Membership activated.', 'rcp' ) );
		if ( empty( $this->get_activated_date() ) ) {
			$this->update( array( 'activated_date' => current_time( 'mysql' ) ) );
		}
		if ( 'active' !== $this->get_status() ) {
			$this->set_status( 'active' );
		}
		$this->add_user_role();
		do_action( 'rcp_membership_post_activate', $this->get_id(), $this );
	}

	protected function add_user_role() {
		$old_role = get_option( 'default_role', 'subscriber' );
		$membership_level = rcp_get_membership_level( $this->get_object_id() );
		$role = $membership_level instanceof Membership_Level ? $membership_level->get_role() : get_option( 'default_role', 'subscriber' );
		$user_id = $this->get_user_id();
		$user = ! empty( $user_id ) ? new WP_User( $user_id ) : false;
		if ( ! $user instanceof WP_User || in_array( $role, $user->roles, true ) ) {
			return;
		}
		if ( 'administrator' !== $old_role ) {
			$user->remove_role( $old_role );
		}
		$user->add_role( apply_filters( 'rcp_default_user_level', $role, $membership_level instanceof Membership_Level ? $membership_level->get_id() : 0 ) );
	}

	public function cancel() {
		if ( 'cancelled' === $this->get_status() ) {
			return;
		}
		do_action( 'rcp_membership_pre_cancel', $this->get_id(), $this );
		$this->set_status( 'cancelled' );
		do_action( 'rcp_membership_post_cancel', $this->get_id(), $this );
	}

	public function expire() {
		$this->set_status( 'expired' );
		$this->set_expiration_date( date( 'Y-m-d H:i:s', strtotime( '-1 day', current_time( 'timestamp' ) ) ) );
	}

	public function get_notes() {
		return $this->notes;
	}

	public function add_note( $note = '' ) {
		$notes = $this->get_notes();
		if ( empty( $notes ) ) {
			$notes = '';
		} else {
			$notes .= "\n\n";
		}
		$notes .= date_i18n( 'F j, Y H:i:s', current_time( 'timestamp' ) ) . ' - ' . $note;
		$this->update( array( 'notes' => $notes ) );
		do_action( 'rcp_membership_add_note', $note, $this->get_id(), $this );
		return true;
	}

	public function get_signup_method() {
		return $this->signup_method;
	}

	public function can_access( $post_id = 0 ) {
		if ( ! rcp_is_restricted_content( $post_id ) ) {
			return apply_filters( 'rcp_membership_can_access', true, $this->get_id(), $post_id, $this );
		}
		if ( $this->is_expired() || ! $this->is_active() ) {
			return apply_filters( 'rcp_membership_can_access', false, $this->get_id(), $post_id, $this );
		}

		$post_type_restrictions = rcp_get_post_type_restrictions( get_post_type( $post_id ) );
		$membership_level_id = $this->get_object_id();

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

		$can_access = true;

		if ( ! empty( $membership_levels ) ) {
			if ( is_string( $membership_levels ) ) {
				switch ( $membership_levels ) {
					case 'any':
						$can_access = ! empty( $membership_level_id );
						break;
					case 'any-paid':
						$can_access = $this->is_paid();
						break;
				}
			} else {
				$can_access = in_array( $membership_level_id, $membership_levels, true );
			}
		}

		if ( ! $this->has_access_level( $access_level ) && $access_level > 0 ) {
			$can_access = false;
		}

		if ( $can_access && ! empty( $user_level ) && 'all' !== strtolower( $user_level[0] ) ) {
			$user_id = $this->get_user_id();
			if ( ! empty( $user_id ) ) {
				$role_access = false;
				foreach ( $user_level as $role ) {
					if ( user_can( $user_id, strtolower( $role ) ) ) {
						$role_access = true;
						break;
					}
				}
				$can_access = $role_access;
			}
		}

		$has_post_restrictions = rcp_has_post_restrictions( $post_id );

		if ( $can_access && ! $has_post_restrictions && rcp_has_term_restrictions( $post_id ) ) {
			$restricted = false;
			$terms = (array) rcp_get_connected_term_ids( $post_id );
			if ( ! empty( $terms ) ) {
				foreach ( $terms as $term_id ) {
					$restrictions = rcp_get_term_restrictions( $term_id );
					if ( empty( $restrictions['paid_only'] ) && empty( $restrictions['subscriptions'] ) && ( empty( $restrictions['access_level'] ) || 'None' === $restrictions['access_level'] ) ) {
						if ( count( $terms ) === 1 ) {
							break;
						}
						continue;
					}
					if ( ! $restricted && ! empty( $restrictions['paid_only'] ) && empty( $restrictions['subscriptions'] ) && empty( $restrictions['access_level'] ) && ( ! $this->is_active() || ! $this->is_paid() ) ) {
						$restricted = true;
						break;
					}
					if ( ! $restricted && ! empty( $restrictions['subscriptions'] ) && ! in_array( $this->get_object_id(), $restrictions['subscriptions'], true ) ) {
						$restricted = true;
						break;
					}
					if ( ! $restricted && ! empty( $restrictions['access_level'] ) && 'None' !== $restrictions['access_level'] ) {
						if ( $restrictions['access_level'] > 0 && ! $this->has_access_level( $restrictions['access_level'] ) ) {
							$restricted = true;
							break;
						}
					}
				}
			}
			if ( $restricted ) {
				$can_access = false;
			}
		} elseif ( ! $can_access && $has_post_restrictions && rcp_has_term_restrictions( $post_id ) ) {
			$allowed = false;
			$terms = (array) rcp_get_connected_term_ids( $post_id );
			if ( ! empty( $terms ) ) {
				foreach ( $terms as $term_id ) {
					$restrictions = rcp_get_term_restrictions( $term_id );
					if ( empty( $restrictions['paid_only'] ) && empty( $restrictions['subscriptions'] ) && ( empty( $restrictions['access_level'] ) || 'None' === $restrictions['access_level'] ) ) {
						if ( count( $terms ) === 1 ) {
							break;
						}
						continue;
					}
					if ( ! $allowed && ! empty( $restrictions['paid_only'] ) && empty( $restrictions['subscriptions'] ) && empty( $restrictions['access_level'] ) && $this->is_active() && $this->is_paid() ) {
						$allowed = true;
						break;
					}
					if ( ! $allowed && ! empty( $restrictions['subscriptions'] ) && in_array( $this->get_object_id(), $restrictions['subscriptions'], true ) ) {
						$allowed = true;
						break;
					}
					if ( ! $allowed && ! empty( $restrictions['access_level'] ) && 'None' !== $restrictions['access_level'] ) {
						if ( $restrictions['access_level'] > 0 && $this->has_access_level( $restrictions['access_level'] ) ) {
							$allowed = true;
							break;
						}
					}
				}
			}
			if ( $allowed ) {
				$can_access = true;
			}
		}

		return apply_filters( 'rcp_membership_can_access', $can_access, $this->get_id(), $post_id, $this );
	}

	public function has_access_level( $access_level_needed = 0 ) {
		$membership_level = rcp_get_membership_level( $this->get_object_id() );
		if ( ! $membership_level instanceof Membership_Level ) {
			return false;
		}
		if ( ( $membership_level->get_access_level() >= $access_level_needed ) || 0 == $access_level_needed ) {
			return true;
		}
		return false;
	}
}
